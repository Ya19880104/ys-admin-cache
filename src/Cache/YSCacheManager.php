<?php
/**
 * 快取管理器
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * 快取管理核心
 */
class YSCacheManager {

    /**
     * 設定
     *
     * @var array<string, mixed>
     */
    private static array $settings = [];

    /**
     * 排除的頁面（絕對不快取）
     */
    private const EXCLUDED_PAGES = [
        'options-general.php?page=ys-admin-cache', // 外掛自己的設定頁面
    ];

    /**
     * 初始化
     *
     * @param array<string, mixed> $settings 設定.
     * @return void
     */
    public static function init( array $settings ): void {
        self::$settings = $settings;

        // 在最早的時機檢查快取
        add_action( 'admin_init', [ __CLASS__, 'maybe_serve_cached' ], 1 );
    }

    /**
     * 檢查並輸出快取內容
     *
     * @return void
     */
    public static function maybe_serve_cached(): void {
        // 權限檢查：僅已登入且有 read 權限的用戶
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return;
        }

        // 檢查是否為可快取請求
        if ( ! self::is_cacheable_request() ) {
            return;
        }

        // 檢查是否為 POST 請求（非預載入）
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! isset( $_POST['ys_admin_cache_prefetch'] ) ) {
            // POST 請求時清除快取
            \YangSheep\AdminCache\Hooks\YSCacheInvalidator::invalidate_all_cache();
            return;
        }

        // 檢查是否為預載入請求
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['ys_admin_cache_prefetch'] ) ) {
            self::handle_prefetch_request();
            return;
        }

        // 檢查是否為刷新請求
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $is_refresh = isset( $_POST['ys_admin_cache_refresh'] ) && '1' === $_POST['ys_admin_cache_refresh'];

        $cache_key = YSCacheKey::generate();
        $cached    = $is_refresh ? false : YSCacheStorage::get( $cache_key );

        if ( false === $cached ) {
            return; // 無快取，讓 OutputBuffer 處理
        }

        // 驗證快取資料完整性
        if ( ! isset( $cached['content'], $cached['generated_at'], $cached['user_id'] ) ) {
            YSCacheStorage::delete( $cache_key );
            return;
        }

        // 驗證用戶
        if ( $cached['user_id'] !== get_current_user_id() ) {
            return;
        }

        // 輸出快取內容
        self::output_cached_content( $cached );
    }

    /**
     * 處理預載入請求
     *
     * @return never
     */
    private static function handle_prefetch_request(): never {
        $cache_key = YSCacheKey::generate();
        $cached    = YSCacheStorage::get( $cache_key );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $is_refresh = isset( $_POST['ys_admin_cache_refresh'] ) && '1' === $_POST['ys_admin_cache_refresh'];

        if ( false !== $cached && ! $is_refresh ) {
            // 快取存在，回傳剩餘時間
            $generated = strtotime( $cached['generated_at'] );
            $remaining = ( self::$settings['duration'] ?? 300 ) - ( time() - $generated );
            $remaining = max( 0, $remaining );

            echo 'prefetched:' . esc_html( $remaining );
            exit;
        }

        // 無快取，開始緩衝
        ob_start( function ( $content ) use ( $cache_key ) {
            if ( strpos( $content, '</html>' ) === false ) {
                return $content;
            }

            // 儲存快取
            $data = [
                'content'      => $content,
                'generated_at' => current_time( 'mysql' ),
                'user_id'      => get_current_user_id(),
                'page'         => YSCacheKey::get_current_page(),
            ];

            YSCacheStorage::set( $cache_key, $data, self::$settings['duration'] ?? 300 );

            return 'prefetching:' . ( self::$settings['duration'] ?? 300 );
        } );

        // 讓 WordPress 繼續處理
    }

    /**
     * 輸出快取內容
     *
     * @param array<string, mixed> $cached 快取資料.
     * @return never
     */
    private static function output_cached_content( array $cached ): never {
        $content = $cached['content'];

        // 顯示快取標籤（含內聯樣式 - 莫蘭迪淺藍灰）
        if ( self::$settings['show_label'] ?? false ) {
            $label_style = '<style>.ys-admin-cache-label{position:fixed;bottom:12px;right:12px;background:#8fa8b8;color:#fff;font-size:11px;font-weight:600;padding:5px 10px;border-radius:4px;z-index:99999;opacity:0.9;pointer-events:none;box-shadow:0 2px 8px rgba(143,168,184,0.4);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}</style>';
            $label       = $label_style . '<div class="ys-admin-cache-label">' . esc_html__( '已快取', 'ys-admin-cache' ) . '</div>';
            $content     = str_replace( '</body>', $label . '</body>', $content );
        }

        // Debug 模式顯示快取資訊
        if ( self::$settings['debug_mode'] ?? false ) {
            $debug_comment = sprintf(
                '<!-- YS Admin Cache: served from cache, generated at %s -->',
                esc_html( $cached['generated_at'] )
            );
            $content = str_replace( '</html>', $debug_comment . "\n</html>", $content );
        }

        // 安全輸出（內容已在儲存時處理過）
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
        exit;
    }

    /**
     * 檢查請求是否可快取
     *
     * @return bool
     */
    public static function is_cacheable_request(): bool {
        // 不快取 AJAX 請求
        if ( wp_doing_ajax() ) {
            return false;
        }

        // 不快取 CRON
        if ( wp_doing_cron() ) {
            return false;
        }

        // 不快取 REST API
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }

        // 取得當前頁面
        $current_page     = YSCacheKey::get_current_page();
        $current_page_raw = YSCacheKey::get_current_page_raw();

        // 檢查是否為排除頁面（外掛自己的設定頁面）
        foreach ( self::EXCLUDED_PAGES as $excluded ) {
            if ( self::match_page( $current_page, $excluded ) ) {
                return false;
            }
            if ( $current_page_raw === $excluded ) {
                return false;
            }
        }

        // 檢查是否在快取頁面清單中
        $cached_pages = self::$settings['cached_pages'] ?? [];
        if ( empty( $cached_pages ) ) {
            return false;
        }

        // 檢查頁面是否在清單中
        foreach ( $cached_pages as $page ) {
            if ( self::match_page( $current_page, $page ) ) {
                return true;
            }
            if ( $current_page_raw === $page ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 比對頁面
     *
     * @param string $current 當前頁面.
     * @param string $pattern 比對模式.
     * @return bool
     */
    private static function match_page( string $current, string $pattern ): bool {
        // 完全比對
        if ( $current === $pattern ) {
            return true;
        }

        // URL 解碼後比對
        $current_decoded = urldecode( $current );
        $pattern_decoded = urldecode( $pattern );

        if ( $current_decoded === $pattern_decoded ) {
            return true;
        }

        // 基礎頁面比對（只比對 .php 部分）
        $current_base = strtok( $current, '?' );
        $pattern_base = strtok( $pattern, '?' );

        // 如果 pattern 包含 post_type 參數，需要完全比對
        if ( str_contains( $pattern, 'post_type=' ) ) {
            // 提取 post_type 值
            $pattern_pt = '';
            if ( preg_match( '/post_type=([^&]+)/', $pattern, $matches ) ) {
                $pattern_pt = $matches[1];
            }

            $current_pt = '';
            if ( preg_match( '/post_type=([^&]+)/', $current, $matches ) ) {
                $current_pt = $matches[1];
            }

            return $current_base === $pattern_base && $current_pt === $pattern_pt;
        }

        // 如果 pattern 包含 page 參數（設定頁面），需要完全比對
        if ( str_contains( $pattern, 'page=' ) ) {
            $pattern_page = '';
            if ( preg_match( '/page=([^&]+)/', $pattern, $matches ) ) {
                $pattern_page = $matches[1];
            }

            $current_page_param = '';
            if ( preg_match( '/page=([^&]+)/', $current, $matches ) ) {
                $current_page_param = $matches[1];
            }

            return $current_base === $pattern_base && $current_page_param === $pattern_page;
        }

        // 簡單頁面只需要基礎比對（且當前頁面沒有 post_type）
        return $current_base === $pattern_base && ! str_contains( $current, 'post_type=' );
    }

    /**
     * 儲存快取
     *
     * @param string $content 頁面內容.
     * @return bool
     */
    public static function save( string $content ): bool {
        if ( ! self::is_cacheable_request() ) {
            return false;
        }

        // 驗證內容完整性
        if ( strpos( $content, '</html>' ) === false ) {
            return false;
        }

        $cache_key = YSCacheKey::generate();
        $data      = [
            'content'      => $content,
            'generated_at' => current_time( 'mysql' ),
            'user_id'      => get_current_user_id(),
            'page'         => YSCacheKey::get_current_page(),
        ];

        $saved = YSCacheStorage::set(
            $cache_key,
            $data,
            self::$settings['duration'] ?? 300
        );

        if ( $saved ) {
            \YangSheep\AdminCache\YSAdminCache::log(
                sprintf( 'Cache saved for page: %s, key: %s', $data['page'], $cache_key )
            );
        }

        return $saved;
    }

    /**
     * 取得設定
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array {
        return self::$settings;
    }
}
