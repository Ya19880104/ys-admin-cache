<?php
/**
 * 安全 Cookie 管理
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * 安全的 Cookie 管理
 *
 * 修復原外掛的 Cookie 安全問題：
 * - 加入 httponly 防止 XSS 存取
 * - 加入 secure 確保 HTTPS 傳輸
 * - 加入 samesite 防止 CSRF
 */
class YSCookieManager {

    /**
     * Cookie 前綴
     */
    private const COOKIE_PREFIX = 'ys_admin_cache_';

    /**
     * 設定安全 Cookie
     *
     * @param string $name   Cookie 名稱（不含前綴）.
     * @param string $value  Cookie 值.
     * @param int    $expiry 過期時間（秒），0 表示 session cookie.
     * @return bool
     */
    public static function set( string $name, string $value, int $expiry = 0 ): bool {
        $cookie_name = self::COOKIE_PREFIX . sanitize_key( $name );

        // 安全 Cookie 選項
        $options = [
            'expires'  => $expiry > 0 ? time() + $expiry : 0,
            'path'     => ADMIN_COOKIE_PATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        return setcookie( $cookie_name, $value, $options );
    }

    /**
     * 取得 Cookie 值
     *
     * @param string $name Cookie 名稱（不含前綴）.
     * @return string|null
     */
    public static function get( string $name ): ?string {
        $cookie_name = self::COOKIE_PREFIX . sanitize_key( $name );

        if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
            return null;
        }

        // 清理並返回值
        return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
    }

    /**
     * 檢查 Cookie 是否存在
     *
     * @param string $name Cookie 名稱（不含前綴）.
     * @return bool
     */
    public static function has( string $name ): bool {
        $cookie_name = self::COOKIE_PREFIX . sanitize_key( $name );
        return isset( $_COOKIE[ $cookie_name ] );
    }

    /**
     * 刪除 Cookie
     *
     * @param string $name Cookie 名稱（不含前綴）.
     * @return bool
     */
    public static function delete( string $name ): bool {
        $cookie_name = self::COOKIE_PREFIX . sanitize_key( $name );

        // 設定過期時間為過去來刪除 cookie
        $options = [
            'expires'  => time() - 3600,
            'path'     => ADMIN_COOKIE_PATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        // 同時從當前請求中移除
        unset( $_COOKIE[ $cookie_name ] );

        return setcookie( $cookie_name, '', $options );
    }

    /**
     * 取得使用者 Session Token
     *
     * @return string
     */
    public static function get_session_token(): string {
        $token = wp_get_session_token();

        if ( empty( $token ) ) {
            return '';
        }

        return sanitize_key( $token );
    }
}
