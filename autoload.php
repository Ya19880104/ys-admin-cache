<?php
/**
 * PSR-4 自動載入器
 *
 * 當 Composer autoload 不可用時使用此檔案。
 * 如果有安裝 Composer，請執行 `composer dump-autoload -o` 以獲得更好的效能。
 *
 * @package YS_Admin_Cache
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
    function ( string $class ): void {
        // 命名空間前綴
        $prefix = 'YangSheep\\AdminCache\\';

        // 基礎目錄
        $base_dir = __DIR__ . '/src/';

        // 檢查類別是否使用此命名空間前綴
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        // 取得相對類別名稱
        $relative_class = substr( $class, $len );

        // 將命名空間分隔符轉換為目錄分隔符，並加上 .php 副檔名
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // 如果檔案存在，載入它
        if ( file_exists( $file ) ) {
            require $file;
        }
    }
);
