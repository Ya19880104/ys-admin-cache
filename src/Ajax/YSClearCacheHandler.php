<?php
/**
 * 清除快取 Ajax 處理器
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Ajax;

use YangSheep\AdminCache\Cache\YSCacheStorage;
use YangSheep\AdminCache\YSAdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * 安全的 Ajax 清除快取處理
 */
class YSClearCacheHandler {

    /**
     * 初始化
     *
     * @return void
     */
    public static function init(): void {
        add_action( 'wp_ajax_ys_admin_cache_clear', [ __CLASS__, 'handle' ] );
    }

    /**
     * 處理清除請求
     *
     * @return void
     */
    public static function handle(): void {
        // 1. 驗證 Nonce（CSRF 保護）
        if ( ! check_ajax_referer( 'ys_admin_cache_action', 'nonce', false ) ) {
            wp_send_json_error(
                [
                    'message' => __( '安全驗證失敗，請重新整理頁面後再試。', 'ys-admin-cache' ),
                ],
                403
            );
        }

        // 2. 權限檢查（Authorization）
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                [
                    'message' => __( '您沒有權限執行此操作。', 'ys-admin-cache' ),
                ],
                403
            );
        }

        // 3. 執行清除
        $result = YSCacheStorage::flush_all();

        if ( $result ) {
            YSAdminCache::log(
                sprintf(
                    'Cache cleared by user %d (%s)',
                    get_current_user_id(),
                    wp_get_current_user()->user_login
                )
            );

            wp_send_json_success(
                [
                    'message' => __( '所有快取已成功清除。', 'ys-admin-cache' ),
                ]
            );
        } else {
            wp_send_json_error(
                [
                    'message' => __( '清除快取時發生錯誤，請稍後再試。', 'ys-admin-cache' ),
                ]
            );
        }
    }
}
