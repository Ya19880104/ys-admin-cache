<?php
/**
 * 輸出緩衝處理
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Hooks;

use YangSheep\AdminCache\Cache\YSCacheManager;

defined( 'ABSPATH' ) || exit;

/**
 * 輸出緩衝管理
 */
class YSOutputBuffer {

    /**
     * 設定
     *
     * @var array<string, mixed>
     */
    private static array $settings = [];

    /**
     * 是否已啟動緩衝
     *
     * @var bool
     */
    private static bool $buffer_started = false;

    /**
     * 初始化
     *
     * @param array<string, mixed> $settings 設定.
     * @return void
     */
    public static function init( array $settings ): void {
        self::$settings = $settings;

        // 在 admin_init 之後啟動緩衝（確保所有權限檢查已完成）
        add_action( 'admin_init', [ __CLASS__, 'maybe_start_buffer' ], 5 );
    }

    /**
     * 可能啟動緩衝
     *
     * @return void
     */
    public static function maybe_start_buffer(): void {
        // 已啟動則跳過
        if ( self::$buffer_started ) {
            return;
        }

        // 權限檢查
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return;
        }

        // 檢查是否為可快取請求
        if ( ! YSCacheManager::is_cacheable_request() ) {
            return;
        }

        // 檢查是否已有快取
        $cache_key = \YangSheep\AdminCache\Cache\YSCacheKey::generate();
        $cached    = \YangSheep\AdminCache\Cache\YSCacheStorage::get( $cache_key );

        if ( false !== $cached ) {
            return; // 已有快取，由 CacheManager 處理
        }

        // 啟動輸出緩衝
        self::$buffer_started = true;
        ob_start( [ __CLASS__, 'capture_output' ] );

        // 確保在關閉時清理緩衝
        add_action( 'shutdown', [ __CLASS__, 'end_buffer' ], 0 );
    }

    /**
     * 捕獲輸出
     *
     * @param string $content 輸出內容.
     * @return string
     */
    public static function capture_output( string $content ): string {
        // 檢查內容完整性
        if ( strpos( $content, '</html>' ) === false ) {
            return $content;
        }

        // 檢查是否為預載入請求
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['ys_admin_cache_prefetch'] ) ) {
            // 儲存快取
            YSCacheManager::save( $content );

            // 回傳預載入狀態
            return 'prefetching:' . ( self::$settings['duration'] ?? 3600 );
        }

        // 儲存快取
        YSCacheManager::save( $content );

        // 標記快取時間
        $timestamp = '<!--ys-admin-cache:' . time() . '-->';
        $content   = str_replace( '</body>', $timestamp . '</body>', $content );

        return $content;
    }

    /**
     * 結束緩衝
     *
     * @return void
     */
    public static function end_buffer(): void {
        if ( self::$buffer_started && ob_get_level() > 0 ) {
            ob_end_flush();
            self::$buffer_started = false;
        }
    }
}
