<?php
/**
 * Cron 預載入處理器
 *
 * 使用 WordPress Cron 主動預載入快取
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Cron;

use YangSheep\AdminCache\Cache\YSCacheKey;
use YangSheep\AdminCache\Cache\YSCacheStorage;
use YangSheep\AdminCache\YSAdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * Cron 預載入
 */
class YSCronPreloader {

    /**
     * Cron Hook 名稱
     */
    public const CRON_HOOK = 'ys_admin_cache_preload';

    /**
     * 預載入 Token Option 名稱
     */
    private const TOKEN_OPTION = 'ys_admin_cache_preload_token';

    /**
     * 初始化
     *
     * @return void
     */
    public static function init(): void {
        // 註冊 Cron Hook
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_preload' ] );

        // 註冊內部預載入端點
        add_action( 'admin_init', [ __CLASS__, 'handle_preload_request' ], 0 );
    }

    /**
     * 安排 Cron 事件
     *
     * @param int $interval 間隔秒數（根據快取時間設定）.
     * @return void
     */
    public static function schedule( int $interval ): void {
        // 清除現有排程
        self::unschedule();

        // 計算下次執行時間（快取過期前 30 秒）
        $next_run = time() + max( 60, $interval - 30 );

        // 安排單次事件（每次執行後會重新安排）
        wp_schedule_single_event( $next_run, self::CRON_HOOK );

        YSAdminCache::log(
            sprintf( 'Cron scheduled: next run in %d seconds', $interval - 30 )
        );
    }

    /**
     * 取消 Cron 排程
     *
     * @return void
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        // 清除所有相關事件
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * 執行預載入（由 Cron 調用）
     *
     * @return void
     */
    public static function run_preload(): void {
        $settings = YSAdminCache::instance()->get_settings();

        // 檢查是否啟用
        if ( empty( $settings['enabled'] ) || empty( $settings['preload_enabled'] ) ) {
            YSAdminCache::log( 'Cron preload skipped: disabled' );
            return;
        }

        $cached_pages = $settings['cached_pages'] ?? [];
        if ( empty( $cached_pages ) ) {
            YSAdminCache::log( 'Cron preload skipped: no pages configured' );
            return;
        }

        YSAdminCache::log( 'Cron preload started' );

        // 取得所有管理員用戶
        $admin_users = get_users( [
            'role__in' => [ 'administrator' ],
            'fields'   => 'ID',
            'number'   => 10, // 限制數量避免過載
        ] );

        if ( empty( $admin_users ) ) {
            YSAdminCache::log( 'Cron preload skipped: no admin users' );
            return;
        }

        // 生成一次性 token
        $token = self::generate_token();

        // 對每個頁面發送預載入請求
        $success_count = 0;
        $fail_count    = 0;

        foreach ( $cached_pages as $page ) {
            foreach ( $admin_users as $user_id ) {
                $result = self::preload_page( $page, $user_id, $token );

                if ( $result ) {
                    ++$success_count;
                } else {
                    ++$fail_count;
                }

                // 短暫延遲避免過載
                usleep( 100000 ); // 0.1 秒
            }
        }

        // 清除 token
        delete_option( self::TOKEN_OPTION );

        YSAdminCache::log(
            sprintf( 'Cron preload completed: %d success, %d failed', $success_count, $fail_count )
        );

        // 重新安排下次執行
        $duration = $settings['duration'] ?? 300;
        self::schedule( $duration );
    }

    /**
     * 預載入單一頁面
     *
     * @param string $page    頁面路徑.
     * @param int    $user_id 用戶 ID.
     * @param string $token   認證 Token.
     * @return bool
     */
    private static function preload_page( string $page, int $user_id, string $token ): bool {
        $url = add_query_arg(
            [
                'ys_preload_token'   => $token,
                'ys_preload_user_id' => $user_id,
            ],
            admin_url( $page )
        );

        // 使用內部 HTTP 請求
        $response = wp_remote_get(
            $url,
            [
                'timeout'   => 30,
                'sslverify' => false,
                'cookies'   => [], // 不帶 cookie，使用 token 認證
            ]
        );

        if ( is_wp_error( $response ) ) {
            YSAdminCache::log(
                sprintf( 'Preload failed for %s (user %d): %s', $page, $user_id, $response->get_error_message() )
            );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );

        // 檢查是否成功
        if ( strpos( $body, 'preload_success' ) !== false ) {
            YSAdminCache::log(
                sprintf( 'Preload success for %s (user %d)', $page, $user_id )
            );
            return true;
        }

        return false;
    }

    /**
     * 處理預載入請求（在 admin_init 最早執行）
     *
     * @return void
     */
    public static function handle_preload_request(): void {
        // 檢查是否為預載入請求
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['ys_preload_token'], $_GET['ys_preload_user_id'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token   = sanitize_text_field( wp_unslash( $_GET['ys_preload_token'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $user_id = absint( $_GET['ys_preload_user_id'] );

        // 驗證 token
        if ( ! self::verify_token( $token ) ) {
            YSAdminCache::log( 'Preload request rejected: invalid token' );
            return;
        }

        // 驗證用戶存在且是管理員
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
            YSAdminCache::log( 'Preload request rejected: invalid user' );
            return;
        }

        // 切換到該用戶
        wp_set_current_user( $user_id );

        // 取得當前頁面
        $current_page = YSCacheKey::get_current_page();

        // 移除 token 參數後的頁面
        $clean_page = remove_query_arg( [ 'ys_preload_token', 'ys_preload_user_id' ], $current_page );
        if ( empty( $clean_page ) || $clean_page === '?' ) {
            $clean_page = strtok( $current_page, '?' );
        }

        YSAdminCache::log(
            sprintf( 'Preload request processing: page=%s, user=%d', $clean_page, $user_id )
        );

        // 生成快取鍵（使用乾淨的頁面名稱）
        $cache_key = sprintf(
            '%d_%s_%s',
            $user_id,
            md5( $clean_page ),
            md5( '' ) // 空的 query string
        );
        $cache_key = sanitize_key( $cache_key );

        // 啟動輸出緩衝
        ob_start( function ( $content ) use ( $cache_key, $clean_page, $user_id ) {
            if ( strpos( $content, '</html>' ) === false ) {
                return $content;
            }

            $settings = YSAdminCache::instance()->get_settings();

            // 儲存快取
            $data = [
                'content'      => $content,
                'generated_at' => current_time( 'mysql' ),
                'user_id'      => $user_id,
                'page'         => $clean_page,
            ];

            $saved = YSCacheStorage::set( $cache_key, $data, $settings['duration'] ?? 300 );

            YSAdminCache::log(
                sprintf( 'Cron preload cache %s: page=%s, user=%d', $saved ? 'saved' : 'failed', $clean_page, $user_id )
            );

            // 返回成功標記
            return 'preload_success';
        } );
    }

    /**
     * 生成預載入 Token
     *
     * @return string
     */
    private static function generate_token(): string {
        $token = wp_generate_password( 32, false );
        update_option( self::TOKEN_OPTION, $token, false );
        return $token;
    }

    /**
     * 驗證 Token
     *
     * @param string $token Token.
     * @return bool
     */
    private static function verify_token( string $token ): bool {
        $stored_token = get_option( self::TOKEN_OPTION, '' );
        return ! empty( $stored_token ) && hash_equals( $stored_token, $token );
    }

    /**
     * 取得下次執行時間
     *
     * @return int|false 時間戳或 false
     */
    public static function get_next_run(): int|false {
        return wp_next_scheduled( self::CRON_HOOK );
    }
}
