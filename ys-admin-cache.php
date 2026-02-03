<?php
/**
 * Plugin Name: YS Admin Cache
 * Plugin URI: https://yangsheep.com.tw/plugins/ys-admin-cache
 * Description: WordPress 後台頁面快取，提升管理效率
 * Version: 1.0.0
 * Author: YANGSHEEP DESIGN
 * Author URI: https://yangsheep.com.tw
 * Text Domain: ys-admin-cache
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package YS_Admin_Cache
 */

defined( 'ABSPATH' ) || exit;

// 常數定義
define( 'YS_ADMIN_CACHE_VERSION', '1.0.0' );
define( 'YS_ADMIN_CACHE_PLUGIN_FILE', __FILE__ );
define( 'YS_ADMIN_CACHE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_ADMIN_CACHE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_ADMIN_CACHE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// 自動載入：優先使用 Composer，否則使用自訂 autoloader
if ( file_exists( YS_ADMIN_CACHE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once YS_ADMIN_CACHE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    require_once YS_ADMIN_CACHE_PLUGIN_DIR . 'autoload.php';
}

/**
 * 初始化外掛
 *
 * @return void
 */
function ys_admin_cache_init(): void {
    // 僅在後台執行
    if ( ! is_admin() ) {
        return;
    }

    \YangSheep\AdminCache\YSAdminCache::init();
}
add_action( 'plugins_loaded', 'ys_admin_cache_init' );

/**
 * 外掛啟用
 *
 * @return void
 */
function ys_admin_cache_activate(): void {
    \YangSheep\AdminCache\YSAdminCache::activate();
}
register_activation_hook( __FILE__, 'ys_admin_cache_activate' );

/**
 * 外掛停用
 *
 * @return void
 */
function ys_admin_cache_deactivate(): void {
    \YangSheep\AdminCache\YSAdminCache::deactivate();
}
register_deactivation_hook( __FILE__, 'ys_admin_cache_deactivate' );

/**
 * 外掛設定連結
 *
 * @param array<string> $links 現有連結
 * @return array<string>
 */
function ys_admin_cache_plugin_action_links( array $links ): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'options-general.php?page=ys-admin-cache' ) ),
        esc_html__( '設定', 'ys-admin-cache' )
    );
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . YS_ADMIN_CACHE_PLUGIN_BASENAME, 'ys_admin_cache_plugin_action_links' );
