<?php
/**
 * 預載入 Ajax 處理器
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Ajax;

use YangSheep\AdminCache\YSAdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax 預載入處理
 */
class YSPreloadHandler {

    /**
     * 初始化
     *
     * @return void
     */
    public static function init(): void {
        // 在所有後台頁面注入預載入腳本
        add_action( 'admin_print_footer_scripts', [ __CLASS__, 'inject_preload_script' ] );
    }

    /**
     * 注入預載入腳本
     *
     * @return void
     */
    public static function inject_preload_script(): void {
        $settings = YSAdminCache::instance()->get_settings();

        // 檢查是否啟用預載入
        if ( empty( $settings['preload_enabled'] ) || empty( $settings['enabled'] ) ) {
            return;
        }

        // 檢查是否有設定快取頁面
        $cached_pages = $settings['cached_pages'] ?? [];
        if ( empty( $cached_pages ) ) {
            return;
        }

        // 確保腳本已載入
        wp_enqueue_script(
            'ys-admin-cache-preload',
            YS_ADMIN_CACHE_PLUGIN_URL . 'assets/js/ys-admin-cache.js',
            [ 'jquery' ],
            YS_ADMIN_CACHE_VERSION,
            true
        );

        // 建構安全的 URL 陣列
        $urls = array_map(
            function ( $page ) {
                return esc_url( admin_url( $page ) );
            },
            $cached_pages
        );

        // 輸出預載入呼叫
        ?>
        <script>
        if (typeof ys_admin_cache_prefetch === 'function') {
            ys_admin_cache_prefetch(<?php echo wp_json_encode( $urls ); ?>);
        }
        </script>
        <?php
    }
}
