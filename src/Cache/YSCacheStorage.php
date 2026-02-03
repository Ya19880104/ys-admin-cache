<?php
/**
 * 快取儲存類別
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * 檔案系統快取儲存
 *
 * 快取存放於 wp-content/uploads/cache/admin/
 */
class YSCacheStorage {

    /**
     * 快取目錄相對路徑
     */
    private const CACHE_SUBDIR = 'cache/admin';

    /**
     * 取得快取目錄路徑
     *
     * @return string
     */
    public static function get_cache_dir(): string {
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'] ?? WP_CONTENT_DIR . '/uploads';

        return trailingslashit( $base_dir ) . self::CACHE_SUBDIR;
    }

    /**
     * 確保快取目錄存在
     *
     * @return bool
     */
    private static function ensure_cache_dir(): bool {
        $cache_dir = self::get_cache_dir();

        if ( ! file_exists( $cache_dir ) ) {
            // 建立目錄（含父目錄）
            if ( ! wp_mkdir_p( $cache_dir ) ) {
                return false;
            }

            // 建立 .htaccess 防止直接存取
            $htaccess = $cache_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $htaccess, "Deny from all\n" );
            }

            // 建立 index.php 防止目錄列表
            $index = $cache_dir . '/index.php';
            if ( ! file_exists( $index ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $index, "<?php\n// Silence is golden.\n" );
            }
        }

        return is_writable( $cache_dir );
    }

    /**
     * 取得快取檔案路徑
     *
     * @param string $key 快取鍵.
     * @return string
     */
    private static function get_cache_file( string $key ): string {
        // 使用 MD5 產生安全的檔案名稱
        $filename = md5( $key ) . '.cache';
        return self::get_cache_dir() . '/' . $filename;
    }

    /**
     * 取得快取
     *
     * @param string $key 快取鍵.
     * @return array<string, mixed>|false
     */
    public static function get( string $key ): array|false {
        $file = self::get_cache_file( $key );

        if ( ! file_exists( $file ) ) {
            return false;
        }

        // 讀取快取檔案
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $file );

        if ( false === $content ) {
            return false;
        }

        // 解碼資料
        $data = @unserialize( $content );

        if ( ! is_array( $data ) || ! isset( $data['expires'], $data['payload'] ) ) {
            // 無效的快取格式，刪除檔案
            self::delete( $key );
            return false;
        }

        // 檢查是否過期
        if ( $data['expires'] > 0 && $data['expires'] < time() ) {
            self::delete( $key );
            return false;
        }

        return $data['payload'];
    }

    /**
     * 設定快取
     *
     * @param string              $key      快取鍵.
     * @param array<string,mixed> $data     快取資料.
     * @param int                 $duration 持續時間（秒）.
     * @return bool
     */
    public static function set( string $key, array $data, int $duration ): bool {
        if ( ! self::ensure_cache_dir() ) {
            return false;
        }

        $file = self::get_cache_file( $key );

        // 組裝快取資料
        $cache_data = [
            'expires' => $duration > 0 ? time() + $duration : 0,
            'payload' => $data,
            'key'     => $key,
            'created' => time(),
        ];

        // 寫入檔案
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $result = file_put_contents( $file, serialize( $cache_data ), LOCK_EX );

        return false !== $result;
    }

    /**
     * 刪除快取
     *
     * @param string $key 快取鍵.
     * @return bool
     */
    public static function delete( string $key ): bool {
        $file = self::get_cache_file( $key );

        if ( file_exists( $file ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return unlink( $file );
        }

        return true;
    }

    /**
     * 依頁面刪除快取
     *
     * @param string $page 頁面名稱.
     * @return int 刪除數量
     */
    public static function delete_by_page( string $page ): int {
        $cache_dir = self::get_cache_dir();

        if ( ! is_dir( $cache_dir ) ) {
            return 0;
        }

        $deleted = 0;
        $files   = glob( $cache_dir . '/*.cache' );

        if ( ! is_array( $files ) ) {
            return 0;
        }

        foreach ( $files as $file ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents( $file );

            if ( false === $content ) {
                continue;
            }

            $data = @unserialize( $content );

            if ( ! is_array( $data ) || ! isset( $data['payload']['page'] ) ) {
                continue;
            }

            // 檢查頁面是否匹配
            if ( str_contains( $data['payload']['page'], $page ) || str_contains( $data['key'] ?? '', $page ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                if ( unlink( $file ) ) {
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    /**
     * 清除所有快取
     *
     * @return bool
     */
    public static function flush_all(): bool {
        $cache_dir = self::get_cache_dir();

        if ( ! is_dir( $cache_dir ) ) {
            return true;
        }

        $files = glob( $cache_dir . '/*.cache' );

        if ( ! is_array( $files ) ) {
            return true;
        }

        $success = true;
        foreach ( $files as $file ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            if ( ! unlink( $file ) ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 清除過期快取
     *
     * @return int 清除數量
     */
    public static function purge_expired(): int {
        $cache_dir = self::get_cache_dir();

        if ( ! is_dir( $cache_dir ) ) {
            return 0;
        }

        $files  = glob( $cache_dir . '/*.cache' );
        $purged = 0;

        if ( ! is_array( $files ) ) {
            return 0;
        }

        $now = time();

        foreach ( $files as $file ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents( $file );

            if ( false === $content ) {
                continue;
            }

            $data = @unserialize( $content );

            if ( ! is_array( $data ) || ! isset( $data['expires'] ) ) {
                // 無效格式，刪除
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                if ( unlink( $file ) ) {
                    ++$purged;
                }
                continue;
            }

            // 檢查是否過期
            if ( $data['expires'] > 0 && $data['expires'] < $now ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                if ( unlink( $file ) ) {
                    ++$purged;
                }
            }
        }

        return $purged;
    }

    /**
     * 取得快取統計
     *
     * @return array{count: int, size: int, path: string}
     */
    public static function get_stats(): array {
        $cache_dir = self::get_cache_dir();

        $stats = [
            'count' => 0,
            'size'  => 0,
            'path'  => $cache_dir,
        ];

        if ( ! is_dir( $cache_dir ) ) {
            return $stats;
        }

        $files = glob( $cache_dir . '/*.cache' );

        if ( ! is_array( $files ) ) {
            return $stats;
        }

        $now = time();

        foreach ( $files as $file ) {
            // 檢查是否過期
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents( $file );

            if ( false === $content ) {
                continue;
            }

            $data = @unserialize( $content );

            // 只計算未過期的快取
            if ( is_array( $data ) && isset( $data['expires'] ) ) {
                if ( 0 === $data['expires'] || $data['expires'] > $now ) {
                    ++$stats['count'];
                    $stats['size'] += filesize( $file );
                }
            }
        }

        return $stats;
    }
}
