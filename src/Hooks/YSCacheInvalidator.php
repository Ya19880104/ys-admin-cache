<?php
/**
 * 快取自動失效處理
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Hooks;

use YangSheep\AdminCache\Cache\YSCacheStorage;
use YangSheep\AdminCache\YSAdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * 監聽事件並自動清除相關快取
 */
class YSCacheInvalidator {

    /**
     * 初始化
     *
     * @return void
     */
    public static function init(): void {
        // 文章相關
        add_action( 'save_post', [ __CLASS__, 'invalidate_posts_cache' ], 10, 2 );
        add_action( 'delete_post', [ __CLASS__, 'invalidate_posts_cache' ] );
        add_action( 'trashed_post', [ __CLASS__, 'invalidate_posts_cache' ] );
        add_action( 'untrashed_post', [ __CLASS__, 'invalidate_posts_cache' ] );

        // 外掛相關
        add_action( 'activated_plugin', [ __CLASS__, 'invalidate_plugins_cache' ] );
        add_action( 'deactivated_plugin', [ __CLASS__, 'invalidate_plugins_cache' ] );
        add_action( 'upgrader_process_complete', [ __CLASS__, 'invalidate_all_cache' ] );

        // 佈景主題相關
        add_action( 'switch_theme', [ __CLASS__, 'invalidate_themes_cache' ] );
        add_action( 'customize_save_after', [ __CLASS__, 'invalidate_themes_cache' ] );

        // 使用者相關
        add_action( 'user_register', [ __CLASS__, 'invalidate_users_cache' ] );
        add_action( 'delete_user', [ __CLASS__, 'invalidate_users_cache' ] );
        add_action( 'profile_update', [ __CLASS__, 'invalidate_users_cache' ] );

        // 留言相關
        add_action( 'comment_post', [ __CLASS__, 'invalidate_comments_cache' ] );
        add_action( 'transition_comment_status', [ __CLASS__, 'invalidate_comments_cache' ] );
        add_action( 'edit_comment', [ __CLASS__, 'invalidate_comments_cache' ] );

        // 分類和標籤
        add_action( 'created_term', [ __CLASS__, 'invalidate_posts_cache' ] );
        add_action( 'edited_term', [ __CLASS__, 'invalidate_posts_cache' ] );
        add_action( 'delete_term', [ __CLASS__, 'invalidate_posts_cache' ] );

        // 選單
        add_action( 'wp_update_nav_menu', [ __CLASS__, 'invalidate_all_cache' ] );

        // 小工具
        add_filter( 'widget_update_callback', [ __CLASS__, 'on_widget_update' ], 10, 4 );

        // 設定變更
        add_action( 'update_option', [ __CLASS__, 'maybe_invalidate_on_option_change' ], 10, 3 );
    }

    /**
     * 文章快取失效
     *
     * @param int           $post_id 文章 ID.
     * @param \WP_Post|null $post    文章物件.
     * @return void
     */
    public static function invalidate_posts_cache( int $post_id, ?\WP_Post $post = null ): void {
        // 排除自動儲存
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // 排除修訂版本
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // 取得文章類型
        $post_type = get_post_type( $post_id );

        // 清除對應的 edit.php 快取
        if ( 'post' === $post_type ) {
            YSCacheStorage::delete_by_page( 'edit.php' );
        } else {
            YSCacheStorage::delete_by_page( 'edit.php_post_type_' . $post_type );
        }

        // 清除首頁快取
        YSCacheStorage::delete_by_page( 'index.php' );

        YSAdminCache::log( "Posts cache invalidated for post {$post_id} (type: {$post_type})" );
    }

    /**
     * 外掛快取失效
     *
     * @return void
     */
    public static function invalidate_plugins_cache(): void {
        YSCacheStorage::delete_by_page( 'plugins.php' );
        YSCacheStorage::delete_by_page( 'index.php' );
        YSAdminCache::log( 'Plugins cache invalidated' );
    }

    /**
     * 佈景主題快取失效
     *
     * @return void
     */
    public static function invalidate_themes_cache(): void {
        YSCacheStorage::delete_by_page( 'themes.php' );
        YSCacheStorage::delete_by_page( 'index.php' );
        YSAdminCache::log( 'Themes cache invalidated' );
    }

    /**
     * 使用者快取失效
     *
     * @return void
     */
    public static function invalidate_users_cache(): void {
        YSCacheStorage::delete_by_page( 'users.php' );
        YSCacheStorage::delete_by_page( 'index.php' );
        YSAdminCache::log( 'Users cache invalidated' );
    }

    /**
     * 留言快取失效
     *
     * @return void
     */
    public static function invalidate_comments_cache(): void {
        YSCacheStorage::delete_by_page( 'edit-comments.php' );
        YSCacheStorage::delete_by_page( 'index.php' );
        YSAdminCache::log( 'Comments cache invalidated' );
    }

    /**
     * 清除所有快取
     *
     * @return void
     */
    public static function invalidate_all_cache(): void {
        YSCacheStorage::flush_all();
        YSAdminCache::log( 'All cache invalidated' );
    }

    /**
     * 小工具更新時清除快取
     *
     * @param array<string,mixed> $instance     新設定.
     * @param array<string,mixed> $new_instance 新實例.
     * @param array<string,mixed> $old_instance 舊實例.
     * @param \WP_Widget          $widget       小工具物件.
     * @return array<string,mixed>
     */
    public static function on_widget_update(
        array $instance,
        array $new_instance,
        array $old_instance,
        \WP_Widget $widget
    ): array {
        self::invalidate_all_cache();
        return $instance;
    }

    /**
     * 設定變更時可能清除快取
     *
     * @param string $option    選項名稱.
     * @param mixed  $old_value 舊值.
     * @param mixed  $new_value 新值.
     * @return void
     */
    public static function maybe_invalidate_on_option_change(
        string $option,
        mixed $old_value,
        mixed $new_value
    ): void {
        // 排除快取自身的設定
        if ( str_starts_with( $option, 'ys_admin_cache_' ) ) {
            return;
        }

        // 排除 transients
        if ( str_starts_with( $option, '_transient' ) ) {
            return;
        }

        // 重要設定變更時清除相關快取
        $options_to_pages = [
            'blogname'              => 'options-general.php',
            'blogdescription'       => 'options-general.php',
            'siteurl'               => 'options-general.php',
            'home'                  => 'options-general.php',
            'admin_email'           => 'options-general.php',
            'users_can_register'    => 'options-general.php',
            'default_role'          => 'options-general.php',
            'timezone_string'       => 'options-general.php',
            'date_format'           => 'options-general.php',
            'time_format'           => 'options-general.php',
            'start_of_week'         => 'options-general.php',
            'WPLANG'                => 'options-general.php',
            'posts_per_page'        => [ 'edit.php', 'index.php' ],
            'default_category'      => 'edit.php',
            'default_post_format'   => 'edit.php',
            'show_on_front'         => 'index.php',
            'page_on_front'         => 'index.php',
            'page_for_posts'        => 'index.php',
            'active_plugins'        => 'plugins.php',
            'template'              => 'themes.php',
            'stylesheet'            => 'themes.php',
            'sidebars_widgets'      => 'widgets.php',
        ];

        if ( isset( $options_to_pages[ $option ] ) ) {
            $pages = (array) $options_to_pages[ $option ];
            foreach ( $pages as $page ) {
                YSCacheStorage::delete_by_page( $page );
            }
            YSAdminCache::log( "Cache invalidated due to option change: {$option}" );
        }
    }
}
