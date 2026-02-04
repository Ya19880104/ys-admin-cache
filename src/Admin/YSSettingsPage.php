<?php
/**
 * 設定頁面
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Admin;

use YangSheep\AdminCache\Cache\YSCacheStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Settings API 整合
 */
class YSSettingsPage {

    /**
     * Option Group
     */
    private const OPTION_GROUP = 'ys_admin_cache_options';

    /**
     * Page Slug
     */
    private const PAGE_SLUG = 'ys-admin-cache';

    /**
     * 初始化
     *
     * @return void
     */
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX 端點
        add_action( 'wp_ajax_ys_admin_cache_get_stats', [ __CLASS__, 'ajax_get_stats' ] );
    }

    /**
     * 新增選單頁面
     *
     * @return void
     */
    public static function add_menu_page(): void {
        add_options_page(
            __( 'YS Admin Cache', 'ys-admin-cache' ),
            __( 'YS Admin Cache', 'ys-admin-cache' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * 註冊設定
     *
     * @return void
     */
    public static function register_settings(): void {
        // 啟用快取
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_enabled',
            [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => 'yes',
            ]
        );

        // 快取持續時間
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_duration',
            [
                'type'              => 'integer',
                'sanitize_callback' => [ __CLASS__, 'sanitize_duration' ],
                'default'           => 300,
            ]
        );

        // 快取頁面清單
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_pages',
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_pages_array' ],
                'default'           => [],
            ]
        );

        // 預載入
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_preload',
            [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => 'yes',
            ]
        );

        // 顯示標籤
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_show_label',
            [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => 'no',
            ]
        );

        // Debug 模式
        register_setting(
            self::OPTION_GROUP,
            'ys_admin_cache_debug',
            [
                'type'              => 'string',
                'sanitize_callback' => [ __CLASS__, 'sanitize_checkbox' ],
                'default'           => 'no',
            ]
        );

        // 設定區段
        add_settings_section(
            'ys_admin_cache_general',
            __( '一般設定', 'ys-admin-cache' ),
            [ __CLASS__, 'render_section_description' ],
            self::PAGE_SLUG
        );

        // 設定欄位
        self::add_settings_fields();
    }

    /**
     * 區段說明
     *
     * @return void
     */
    public static function render_section_description(): void {
        echo '<p>' . esc_html__( '設定後台頁面快取功能，加速後台載入速度。', 'ys-admin-cache' ) . '</p>';
    }

    /**
     * 新增設定欄位
     *
     * @return void
     */
    private static function add_settings_fields(): void {
        // 啟用快取
        add_settings_field(
            'ys_admin_cache_enabled',
            __( '啟用快取', 'ys-admin-cache' ),
            [ __CLASS__, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general',
            [
                'id'          => 'ys_admin_cache_enabled',
                'description' => __( '啟用後台頁面快取功能', 'ys-admin-cache' ),
            ]
        );

        // 快取持續時間
        add_settings_field(
            'ys_admin_cache_duration',
            __( '快取持續時間', 'ys-admin-cache' ),
            [ __CLASS__, 'render_duration_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general'
        );

        // 快取頁面
        add_settings_field(
            'ys_admin_cache_pages',
            __( '快取頁面', 'ys-admin-cache' ),
            [ __CLASS__, 'render_pages_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general'
        );

        // 預載入
        add_settings_field(
            'ys_admin_cache_preload',
            __( '自動預載入', 'ys-admin-cache' ),
            [ __CLASS__, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general',
            [
                'id'          => 'ys_admin_cache_preload',
                'description' => __( '在背景自動預載入快取頁面', 'ys-admin-cache' ),
            ]
        );

        // 顯示標籤
        add_settings_field(
            'ys_admin_cache_show_label',
            __( '顯示快取標籤', 'ys-admin-cache' ),
            [ __CLASS__, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general',
            [
                'id'          => 'ys_admin_cache_show_label',
                'description' => __( '在快取頁面右下角顯示「已快取」標籤', 'ys-admin-cache' ),
            ]
        );

        // Debug 模式
        add_settings_field(
            'ys_admin_cache_debug',
            __( 'Debug 模式', 'ys-admin-cache' ),
            [ __CLASS__, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'ys_admin_cache_general',
            [
                'id'          => 'ys_admin_cache_debug',
                'description' => __( '記錄快取操作到 debug.log', 'ys-admin-cache' ),
            ]
        );
    }

    /**
     * 清理 checkbox 值
     *
     * @param mixed $value 輸入值.
     * @return string
     */
    public static function sanitize_checkbox( mixed $value ): string {
        return in_array( $value, [ 'yes', 'no', '1', '' ], true )
            ? ( in_array( $value, [ 'yes', '1' ], true ) ? 'yes' : 'no' )
            : 'no';
    }

    /**
     * 清理持續時間
     *
     * @param mixed $value 輸入值.
     * @return int
     */
    public static function sanitize_duration( mixed $value ): int {
        $duration = absint( $value );

        // 最短 60 秒，最長 7 天（604800 秒）
        $min = 60;
        $max = 604800;

        if ( $duration < $min ) {
            return $min;
        }

        if ( $duration > $max ) {
            return $max;
        }

        return $duration;
    }

    /**
     * 清理頁面陣列
     *
     * @param mixed $value 輸入值.
     * @return array<string>
     */
    public static function sanitize_pages_array( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        // 允許的後台頁面清單
        $allowed_pages = self::get_admin_pages();

        $sanitized = [];
        foreach ( $value as $page ) {
            // 清理頁面值（保留 ? 和 = 字元）
            $page = sanitize_text_field( $page );

            // 白名單驗證：只允許在清單中的頁面
            if ( in_array( $page, $allowed_pages, true ) ) {
                $sanitized[] = $page;
            }
        }

        return array_values( array_unique( $sanitized ) );
    }

    /**
     * 取得可快取的後台頁面
     *
     * @return array<string>
     */
    public static function get_admin_pages(): array {
        $pages = [
            'index.php',
            'edit.php',
            'upload.php',
            'edit-comments.php',
            'users.php',
            'plugins.php',
            'themes.php',
            'options-general.php',
        ];

        // 加入自訂文章類型
        $post_types = get_post_types( [ 'show_in_menu' => true ], 'objects' );
        foreach ( $post_types as $post_type ) {
            if ( 'post' === $post_type->name || 'attachment' === $post_type->name ) {
                continue;
            }
            $pages[] = 'edit.php?post_type=' . $post_type->name;
        }

        return $pages;
    }

    /**
     * 渲染頁面
     *
     * @return void
     */
    public static function render_page(): void {
        // 權限檢查
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( '您沒有權限存取此頁面。', 'ys-admin-cache' ) );
        }

        $debug_enabled = get_option( 'ys_admin_cache_debug', 'no' ) === 'yes';
        ?>
        <div class="wrap ys-admin-cache-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( '儲存設定', 'ys-admin-cache' ) );
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( '快取操作', 'ys-admin-cache' ); ?></h2>
            <p>
                <button type="button"
                        id="ys-clear-all-cache"
                        class="button button-secondary">
                    <?php esc_html_e( '清除所有快取', 'ys-admin-cache' ); ?>
                </button>
                <span id="ys-clear-cache-status" class="ys-status-message"></span>
            </p>

            <hr>

            <h2><?php esc_html_e( '快取統計', 'ys-admin-cache' ); ?></h2>
            <div id="ys-cache-stats-container">
                <?php self::render_cache_stats(); ?>
            </div>

            <hr>

            <h2><?php esc_html_e( '自動預載入狀態', 'ys-admin-cache' ); ?></h2>
            <?php self::render_cron_status(); ?>

            <?php if ( $debug_enabled ) : ?>
            <hr>

            <h2><?php esc_html_e( 'Debug 日誌', 'ys-admin-cache' ); ?></h2>
            <div class="ys-debug-notice">
                <p>
                    <?php esc_html_e( '日誌檔案位置：', 'ys-admin-cache' ); ?>
                    <code><?php echo esc_html( \YangSheep\AdminCache\Utils\YSLogger::get_log_file_path() ); ?></code>
                </p>
            </div>
            <?php endif; ?>

            <hr>

            <div class="ys-about-section">
                <p>
                    <?php
                    printf(
                        /* translators: %s: version number */
                        esc_html__( 'YS Admin Cache v%s', 'ys-admin-cache' ),
                        esc_html( YS_ADMIN_CACHE_VERSION )
                    );
                    ?>
                    &nbsp;|&nbsp;
                    <?php esc_html_e( '開發者：YANGSHEEP DESIGN', 'ys-admin-cache' ); ?>
                    &nbsp;|&nbsp;
                    <a href="https://yangsheep.com.tw" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( '官方網站', 'ys-admin-cache' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染快取統計
     *
     * @return void
     */
    public static function render_cache_stats(): void {
        $stats = YSCacheStorage::get_stats();
        ?>
        <div class="ys-cache-stats-card">
            <div class="ys-stats-header">
                <h3><?php esc_html_e( '即時統計', 'ys-admin-cache' ); ?></h3>
                <span class="ys-stats-refresh">
                    <?php esc_html_e( '每 10 秒自動更新', 'ys-admin-cache' ); ?>
                    <span id="ys-stats-countdown">(10)</span>
                </span>
            </div>
            <div class="ys-stats-grid">
                <div class="ys-stat-item">
                    <span class="ys-stat-value" id="ys-stat-count"><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></span>
                    <span class="ys-stat-label"><?php esc_html_e( '快取項目', 'ys-admin-cache' ); ?></span>
                </div>
                <div class="ys-stat-item">
                    <span class="ys-stat-value" id="ys-stat-size"><?php echo esc_html( size_format( $stats['size'] ) ); ?></span>
                    <span class="ys-stat-label"><?php esc_html_e( '佔用空間', 'ys-admin-cache' ); ?></span>
                </div>
                <div class="ys-stat-item">
                    <span class="ys-stat-value" id="ys-stat-path"><?php echo esc_html( basename( $stats['path'] ) ); ?></span>
                    <span class="ys-stat-label"><?php esc_html_e( '儲存目錄', 'ys-admin-cache' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染 Cron 狀態
     *
     * @return void
     */
    public static function render_cron_status(): void {
        $preload_enabled = get_option( 'ys_admin_cache_preload', 'yes' ) === 'yes';
        $cache_enabled   = get_option( 'ys_admin_cache_enabled', 'yes' ) === 'yes';
        $duration        = absint( get_option( 'ys_admin_cache_duration', 300 ) );

        // 檢查並重新排程過期的 Cron
        $next_run = \YangSheep\AdminCache\Cron\YSCronPreloader::get_next_run();
        if ( $preload_enabled && $cache_enabled ) {
            if ( ! $next_run || ( $next_run - time() ) < -60 ) {
                \YangSheep\AdminCache\Cron\YSCronPreloader::schedule( $duration );
                $next_run = \YangSheep\AdminCache\Cron\YSCronPreloader::get_next_run();
            }
        }
        ?>
        <div class="ys-cache-stats-card">
            <div class="ys-stats-grid">
                <div class="ys-stat-item">
                    <span class="ys-stat-value">
                        <?php if ( $preload_enabled && $cache_enabled ) : ?>
                            <span style="color: var(--ys-success, #5a9a6b);">✓</span>
                        <?php else : ?>
                            <span style="color: var(--ys-error, #b85c5c);">✗</span>
                        <?php endif; ?>
                    </span>
                    <span class="ys-stat-label"><?php esc_html_e( '預載入狀態', 'ys-admin-cache' ); ?></span>
                </div>
                <div class="ys-stat-item">
                    <span class="ys-stat-value" id="ys-cron-next-run">
                        <?php
                        if ( $next_run ) {
                            $time_diff = $next_run - time();
                            if ( $time_diff > 0 ) {
                                /* translators: %d: seconds */
                                echo esc_html( sprintf( __( '%d 秒', 'ys-admin-cache' ), $time_diff ) );
                            } else {
                                esc_html_e( '執行中', 'ys-admin-cache' );
                            }
                        } else {
                            echo '—';
                        }
                        ?>
                    </span>
                    <span class="ys-stat-label"><?php esc_html_e( '下次執行', 'ys-admin-cache' ); ?></span>
                </div>
                <div class="ys-stat-item">
                    <span class="ys-stat-value">
                        <?php echo esc_html( human_time_diff( 0, $duration ) ); ?>
                    </span>
                    <span class="ys-stat-label"><?php esc_html_e( '更新間隔', 'ys-admin-cache' ); ?></span>
                </div>
            </div>
            <?php if ( ! $preload_enabled || ! $cache_enabled ) : ?>
            <p class="description" style="margin-top: 15px; text-align: center;">
                <?php esc_html_e( '請啟用「自動預載入」選項以使用 Cron 自動更新快取', 'ys-admin-cache' ); ?>
            </p>
            <?php else : ?>
            <p class="description" style="margin-top: 15px; text-align: center;">
                <?php esc_html_e( 'Cron 會自動在快取過期前更新，無需手動操作', 'ys-admin-cache' ); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX 取得快取統計
     *
     * @return void
     */
    public static function ajax_get_stats(): void {
        // 驗證 nonce
        if ( ! check_ajax_referer( 'ys_admin_cache_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }

        // 權限檢查
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        $stats           = YSCacheStorage::get_stats();
        $preload_enabled = get_option( 'ys_admin_cache_preload', 'yes' ) === 'yes';
        $cache_enabled   = get_option( 'ys_admin_cache_enabled', 'yes' ) === 'yes';
        $duration        = absint( get_option( 'ys_admin_cache_duration', 300 ) );

        // 檢查並重新排程過期的 Cron
        $next_run = \YangSheep\AdminCache\Cron\YSCronPreloader::get_next_run();
        if ( $preload_enabled && $cache_enabled ) {
            if ( ! $next_run || ( $next_run - time() ) < -60 ) {
                \YangSheep\AdminCache\Cron\YSCronPreloader::schedule( $duration );
                $next_run = \YangSheep\AdminCache\Cron\YSCronPreloader::get_next_run();
            }
        }

        // 計算下次執行時間文字（顯示秒數）
        $cron_next_run = '—';
        if ( $next_run ) {
            $time_diff = $next_run - time();
            if ( $time_diff > 0 ) {
                /* translators: %d: seconds */
                $cron_next_run = sprintf( __( '%d 秒', 'ys-admin-cache' ), $time_diff );
            } else {
                $cron_next_run = __( '執行中', 'ys-admin-cache' );
            }
        }

        wp_send_json_success( [
            'count'         => number_format_i18n( $stats['count'] ),
            'size'          => size_format( $stats['size'] ),
            'path'          => basename( $stats['path'] ),
            'cron_next_run' => $cron_next_run,
        ] );
    }

    /**
     * 渲染 Checkbox 欄位
     *
     * @param array<string,string> $args 參數.
     * @return void
     */
    public static function render_checkbox_field( array $args ): void {
        $id    = $args['id'] ?? '';
        $value = get_option( $id, 'no' );
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( $id ); ?>"
                   value="yes"
                   <?php checked( $value, 'yes' ); ?>>
            <?php echo esc_html( $args['description'] ?? '' ); ?>
        </label>
        <?php
    }

    /**
     * 渲染持續時間欄位
     *
     * @return void
     */
    public static function render_duration_field(): void {
        $value = get_option( 'ys_admin_cache_duration', 300 );
        ?>
        <input type="number"
               name="ys_admin_cache_duration"
               id="ys_admin_cache_duration"
               value="<?php echo esc_attr( $value ); ?>"
               min="60"
               max="604800"
               step="1"
               class="small-text">
        <span><?php esc_html_e( '秒', 'ys-admin-cache' ); ?></span>

        <p class="description">
            <?php esc_html_e( '快取項目的有效時間（最短 60 秒，最長 7 天）', 'ys-admin-cache' ); ?>
        </p>

        <p class="description">
            <strong><?php esc_html_e( '常用設定：', 'ys-admin-cache' ); ?></strong>
            <button type="button" class="button-link ys-duration-preset" data-value="60">1 <?php esc_html_e( '分鐘', 'ys-admin-cache' ); ?></button> |
            <button type="button" class="button-link ys-duration-preset" data-value="300">5 <?php esc_html_e( '分鐘', 'ys-admin-cache' ); ?></button> |
            <button type="button" class="button-link ys-duration-preset" data-value="1800">30 <?php esc_html_e( '分鐘', 'ys-admin-cache' ); ?></button> |
            <button type="button" class="button-link ys-duration-preset" data-value="3600">1 <?php esc_html_e( '小時', 'ys-admin-cache' ); ?></button> |
            <button type="button" class="button-link ys-duration-preset" data-value="86400">24 <?php esc_html_e( '小時', 'ys-admin-cache' ); ?></button>
        </p>
        <?php
    }

    /**
     * 渲染頁面選擇欄位
     *
     * @return void
     */
    public static function render_pages_field(): void {
        $selected = (array) get_option( 'ys_admin_cache_pages', [] );
        $pages    = self::get_admin_pages();

        $page_labels = [
            'index.php'           => __( '控制台', 'ys-admin-cache' ),
            'edit.php'            => __( '文章列表', 'ys-admin-cache' ),
            'upload.php'          => __( '媒體庫', 'ys-admin-cache' ),
            'edit-comments.php'   => __( '留言', 'ys-admin-cache' ),
            'users.php'           => __( '使用者', 'ys-admin-cache' ),
            'plugins.php'         => __( '外掛', 'ys-admin-cache' ),
            'themes.php'          => __( '佈景主題', 'ys-admin-cache' ),
            'options-general.php' => __( '設定', 'ys-admin-cache' ),
        ];

        echo '<fieldset class="ys-pages-fieldset">';
        echo '<label class="ys-select-all">';
        echo '<input type="checkbox" id="ys-select-all-pages"> ';
        echo '<strong>' . esc_html__( '全選 / 取消全選', 'ys-admin-cache' ) . '</strong>';
        echo '</label>';
        echo '<hr style="margin: 10px 0;">';

        foreach ( $pages as $page ) {
            // 處理自訂文章類型標籤
            if ( str_starts_with( $page, 'edit.php?post_type=' ) ) {
                $post_type_name = str_replace( 'edit.php?post_type=', '', $page );
                $post_type_obj  = get_post_type_object( $post_type_name );
                $label          = $post_type_obj ? $post_type_obj->labels->name : $post_type_name;
            } else {
                $label = $page_labels[ $page ] ?? $page;
            }
            ?>
            <label class="ys-page-checkbox">
                <input type="checkbox"
                       name="ys_admin_cache_pages[]"
                       value="<?php echo esc_attr( $page ); ?>"
                       class="ys-page-item"
                       <?php checked( in_array( $page, $selected, true ) ); ?>>
                <?php echo esc_html( $label ); ?>
                <code>(<?php echo esc_html( $page ); ?>)</code>
            </label>
            <?php
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__( '選擇要啟用快取的後台頁面', 'ys-admin-cache' ) . '</p>';
    }

    /**
     * 載入資源
     *
     * @param string $hook 當前頁面 hook.
     * @return void
     */
    public static function enqueue_assets( string $hook ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ys-admin-cache-settings',
            YS_ADMIN_CACHE_PLUGIN_URL . 'assets/css/ys-admin-cache.css',
            [],
            YS_ADMIN_CACHE_VERSION
        );

        wp_enqueue_script(
            'ys-admin-cache-settings',
            YS_ADMIN_CACHE_PLUGIN_URL . 'assets/js/ys-admin-cache.js',
            [ 'jquery' ],
            YS_ADMIN_CACHE_VERSION,
            true
        );

        wp_localize_script(
            'ys-admin-cache-settings',
            'ys_admin_cache_params',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ys_admin_cache_action' ),
                'labels'   => [
                    'clearing'      => __( '清除中...', 'ys-admin-cache' ),
                    'cleared'       => __( '快取已清除', 'ys-admin-cache' ),
                    'error'         => __( '發生錯誤', 'ys-admin-cache' ),
                    'confirm_clear' => __( '確定要清除所有快取嗎？', 'ys-admin-cache' ),
                ],
            ]
        );
    }
}
