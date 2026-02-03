<?php
/**
 * YS Admin Cache 解安裝
 *
 * 此檔案在使用者從 WordPress 刪除外掛時執行。
 * 負責清理所有外掛建立的資料。
 *
 * @package YS_Admin_Cache
 */

// 安全檢查：確保是由 WordPress 呼叫
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 遞迴刪除目錄
 *
 * @param string $dir 目錄路徑.
 * @return bool
 */
function ys_admin_cache_rmdir_recursive( string $dir ): bool {
    if ( ! is_dir( $dir ) ) {
        return true;
    }

    $items = scandir( $dir );
    if ( false === $items ) {
        return false;
    }

    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $path = $dir . '/' . $item;

        if ( is_dir( $path ) ) {
            ys_admin_cache_rmdir_recursive( $path );
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink( $path );
        }
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    return rmdir( $dir );
}

/**
 * 清理單一站點的資料
 *
 * @return void
 */
function ys_admin_cache_cleanup_site(): void {
    // 刪除設定選項
    delete_option( 'ys_admin_cache_enabled' );
    delete_option( 'ys_admin_cache_duration' );
    delete_option( 'ys_admin_cache_pages' );
    delete_option( 'ys_admin_cache_preload' );
    delete_option( 'ys_admin_cache_show_label' );
    delete_option( 'ys_admin_cache_debug' );

    // 刪除快取檔案目錄
    $upload_dir = wp_upload_dir();
    $cache_dir  = $upload_dir['basedir'] . '/cache/admin';

    if ( is_dir( $cache_dir ) ) {
        ys_admin_cache_rmdir_recursive( $cache_dir );
    }

    // 如果 cache 目錄為空，也刪除它
    $parent_cache_dir = $upload_dir['basedir'] . '/cache';
    if ( is_dir( $parent_cache_dir ) ) {
        $remaining = scandir( $parent_cache_dir );
        if ( is_array( $remaining ) && count( $remaining ) <= 2 ) {
            // 只有 . 和 ..
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
            rmdir( $parent_cache_dir );
        }
    }
}

// 執行清理
ys_admin_cache_cleanup_site();

// 清理 Multisite
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );

    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        ys_admin_cache_cleanup_site();
        restore_current_blog();
    }
}
