<?php
/**
 * 快取鍵生成器
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * 安全的快取鍵生成
 */
class YSCacheKey {

    /**
     * 生成快取鍵
     *
     * 格式: {user_id}_{page_hash}_{query_hash}
     *
     * @param string|null $page 指定頁面，null 則自動偵測.
     * @return string
     */
    public static function generate( ?string $page = null ): string {
        $user_id = get_current_user_id();
        $page    = $page ?? self::get_current_page();
        $query   = self::get_query_string();

        // 使用 md5 來避免特殊字元問題
        $key = sprintf(
            '%d_%s_%s',
            $user_id,
            md5( $page ),
            md5( $query )
        );

        return sanitize_key( $key );
    }

    /**
     * 取得當前頁面（模仿原外掛的邏輯）
     *
     * @return string
     */
    public static function get_current_page(): string {
        // 使用與原外掛相同的方式取得當前 URL
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $current_url = add_query_arg( null, null );

        // 提取路徑的最後部分（與原外掛相同）
        $parts = explode( '/', $current_url );
        $page  = end( $parts );

        // 如果為空，使用 pagenow
        if ( empty( $page ) ) {
            global $pagenow;
            $page = $pagenow ?? 'index.php';
        }

        return $page;
    }

    /**
     * 取得原始當前頁面（用於比對）
     *
     * @return string
     */
    public static function get_current_page_raw(): string {
        global $pagenow;

        $page = $pagenow ?? '';

        // 處理 post type 頁面
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'edit.php' === $page && isset( $_GET['post_type'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
            $page      = 'edit.php?post_type=' . $post_type;
        }

        // 處理 options-general.php 的子頁面
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'options-general.php' === $page && isset( $_GET['page'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $sub_page = sanitize_key( wp_unslash( $_GET['page'] ) );
            $page     = 'options-general.php?page=' . $sub_page;
        }

        return $page;
    }

    /**
     * 取得用於快取的查詢字串
     *
     * 排除動態參數
     *
     * @return string
     */
    private static function get_query_string(): string {
        // 排除的參數（這些會造成快取碎片化）
        $excluded = [
            '_wpnonce',
            '_wp_http_referer',
            'wp_http_referer',
            'action',
            'action2',
            '_locale',
            'message',
            'settings-updated',
            'updated',
            'deleted',
            'trashed',
            'untrashed',
            'ids',
            'locked',
            'skipped',
            'page', // 排除 page 參數，已在頁面識別中處理
        ];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_args = $_GET;

        foreach ( $excluded as $param ) {
            unset( $query_args[ $param ] );
        }

        // 排序確保一致性
        ksort( $query_args );

        // 清理所有值
        $query_args = array_map( 'sanitize_text_field', $query_args );

        return http_build_query( $query_args );
    }

    /**
     * 解析快取鍵
     *
     * @param string $key 快取鍵.
     * @return array{user_id: int, page_hash: string, query_hash: string}|null
     */
    public static function parse( string $key ): ?array {
        $parts = explode( '_', $key, 3 );

        if ( count( $parts ) !== 3 ) {
            return null;
        }

        return [
            'user_id'    => absint( $parts[0] ),
            'page_hash'  => $parts[1],
            'query_hash' => $parts[2],
        ];
    }
}
