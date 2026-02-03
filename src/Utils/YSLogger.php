<?php
/**
 * 日誌記錄器
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Debug 日誌記錄
 *
 * 日誌寫入 wp-content/uploads/wc-logs/ys-admin-cache-{date}.log
 */
class YSLogger {

    /**
     * 日誌檔案名稱前綴
     */
    private const LOG_PREFIX = 'ys-admin-cache';

    /**
     * 日誌等級
     */
    private const LEVELS = [
        'debug'   => 0,
        'info'    => 1,
        'warning' => 2,
        'error'   => 3,
    ];

    /**
     * 取得日誌目錄路徑
     *
     * @return string
     */
    public static function get_log_dir(): string {
        // 優先使用 WooCommerce 的日誌目錄
        if ( defined( 'WC_LOG_DIR' ) ) {
            return WC_LOG_DIR;
        }

        // 否則使用 uploads/wc-logs
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . 'wc-logs/';
    }

    /**
     * 取得日誌檔案路徑
     *
     * @return string
     */
    public static function get_log_file_path(): string {
        $date = current_time( 'Y-m-d' );
        $hash = wp_hash( self::LOG_PREFIX );
        $hash = substr( $hash, 0, 8 );

        return self::get_log_dir() . self::LOG_PREFIX . '-' . $date . '-' . $hash . '.log';
    }

    /**
     * 確保日誌目錄存在
     *
     * @return bool
     */
    private static function ensure_log_dir(): bool {
        $log_dir = self::get_log_dir();

        if ( ! file_exists( $log_dir ) ) {
            if ( ! wp_mkdir_p( $log_dir ) ) {
                return false;
            }

            // 建立 .htaccess 防止直接存取
            $htaccess = $log_dir . '.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $htaccess, "deny from all\n" );
            }

            // 建立 index.html
            $index = $log_dir . 'index.html';
            if ( ! file_exists( $index ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $index, '' );
            }
        }

        return is_writable( $log_dir );
    }

    /**
     * 記錄日誌
     *
     * @param string $message 訊息.
     * @param string $level   等級 (debug, info, warning, error).
     * @return void
     */
    public static function log( string $message, string $level = 'info' ): void {
        // 確保目錄存在
        if ( ! self::ensure_log_dir() ) {
            return;
        }

        // 驗證等級
        if ( ! isset( self::LEVELS[ $level ] ) ) {
            $level = 'info';
        }

        // 格式化訊息
        $formatted = sprintf(
            "[%s] [%s] %s\n",
            current_time( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $message
        );

        // 寫入日誌檔案
        $log_file = self::get_log_file_path();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
    }

    /**
     * Debug 等級日誌
     *
     * @param string $message 訊息.
     * @return void
     */
    public static function debug( string $message ): void {
        self::log( $message, 'debug' );
    }

    /**
     * Info 等級日誌
     *
     * @param string $message 訊息.
     * @return void
     */
    public static function info( string $message ): void {
        self::log( $message, 'info' );
    }

    /**
     * Warning 等級日誌
     *
     * @param string $message 訊息.
     * @return void
     */
    public static function warning( string $message ): void {
        self::log( $message, 'warning' );
    }

    /**
     * Error 等級日誌
     *
     * @param string $message 訊息.
     * @return void
     */
    public static function error( string $message ): void {
        self::log( $message, 'error' );
    }

    /**
     * 記錄陣列或物件
     *
     * @param mixed  $data    資料.
     * @param string $label   標籤.
     * @param string $level   等級.
     * @return void
     */
    public static function dump( mixed $data, string $label = 'Data', string $level = 'debug' ): void {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        $formatted = $label . ': ' . print_r( $data, true );
        self::log( $formatted, $level );
    }

    /**
     * 清除舊日誌（保留最近 7 天）
     *
     * @return int 清除數量
     */
    public static function cleanup_old_logs(): int {
        $log_dir = self::get_log_dir();

        if ( ! is_dir( $log_dir ) ) {
            return 0;
        }

        $files   = glob( $log_dir . self::LOG_PREFIX . '-*.log' );
        $deleted = 0;
        $cutoff  = strtotime( '-7 days' );

        if ( ! is_array( $files ) ) {
            return 0;
        }

        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $cutoff ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                if ( unlink( $file ) ) {
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }
}
