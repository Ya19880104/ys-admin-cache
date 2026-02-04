<?php
/**
 * 快取自動失效處理
 *
 * 簡化版本 - 參考原始 wp-admin-cache 外掛的實現
 * 只在關鍵事件時清除所有快取，避免過度複雜的邏輯
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Hooks;

use YangSheep\AdminCache\Cache\YSCacheStorage;
use YangSheep\AdminCache\YSAdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * 監聽事件並自動清除快取
 */
class YSCacheInvalidator {

    /**
     * 初始化
     *
     * 只監聽關鍵事件，避免快取被過度清除
     * 原始外掛只監聽：activated_plugin, deactivated_plugin, wp_insert_post, widget_update, upgrader
     *
     * @return void
     */
    public static function init(): void {
        // 外掛啟用/停用
        add_action( 'activated_plugin', [ __CLASS__, 'invalidate_all_cache' ] );
        add_action( 'deactivated_plugin', [ __CLASS__, 'invalidate_all_cache' ] );

        // 外掛/主題升級完成
        add_action( 'upgrader_process_complete', [ __CLASS__, 'invalidate_all_cache' ] );

        // 小工具更新
        add_filter( 'widget_update_callback', [ __CLASS__, 'on_widget_update' ], 10, 4 );

        // 注意：不監聽 save_post 等頻繁觸發的事件
        // 因為：
        // 1. 後台列表頁的內容變更通常不影響用戶體驗
        // 2. 快取會自然過期
        // 3. 用戶可以手動清除快取
        // 4. 避免在大量內容更新時造成效能問題
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
}
