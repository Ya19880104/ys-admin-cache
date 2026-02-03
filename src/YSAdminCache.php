<?php
/**
 * YS Admin Cache 主類別
 *
 * @package YS_Admin_Cache
 */

namespace YangSheep\AdminCache;

defined( 'ABSPATH' ) || exit;

/**
 * 主類別 - 單例模式
 */
final class YSAdminCache {

    /**
     * 單例實例
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * 設定快取
     *
     * @var array<string, mixed>
     */
    private array $settings = [];

    /**
     * 取得單例實例
     *
     * @return self
     */
    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化外掛
     *
     * @return void
     */
    public static function init(): void {
        self::instance();
    }

    /**
     * 建構子
     */
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * 載入設定
     *
     * @return void
     */
    private function load_settings(): void {
        $this->settings = [
            'enabled'         => get_option( 'ys_admin_cache_enabled', 'yes' ) === 'yes',
            'duration'        => absint( get_option( 'ys_admin_cache_duration', 300 ) ),
            'cached_pages'    => (array) get_option( 'ys_admin_cache_pages', [] ),
            'preload_enabled' => get_option( 'ys_admin_cache_preload', 'yes' ) === 'yes',
            'show_label'      => get_option( 'ys_admin_cache_show_label', 'no' ) === 'yes',
            'debug_mode'      => get_option( 'ys_admin_cache_debug', 'no' ) === 'yes',
        ];
    }

    /**
     * 初始化 Hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        // 設定頁面
        Admin\YSSettingsPage::init();

        // 快取核心
        if ( $this->settings['enabled'] ) {
            Cache\YSCacheManager::init( $this->settings );
            Hooks\YSOutputBuffer::init( $this->settings );
            Hooks\YSCacheInvalidator::init();
        }

        // Ajax 處理
        Ajax\YSPreloadHandler::init();
        Ajax\YSClearCacheHandler::init();
    }

    /**
     * 外掛啟用
     *
     * @return void
     */
    public static function activate(): void {
        // 設定預設值
        add_option( 'ys_admin_cache_enabled', 'yes' );
        add_option( 'ys_admin_cache_duration', 3600 );
        add_option( 'ys_admin_cache_pages', [] );
        add_option( 'ys_admin_cache_preload', 'yes' );
        add_option( 'ys_admin_cache_show_label', 'no' );
        add_option( 'ys_admin_cache_debug', 'no' );

        // 清除快取
        Cache\YSCacheStorage::flush_all();
    }

    /**
     * 外掛停用
     *
     * @return void
     */
    public static function deactivate(): void {
        // 清除所有快取（保留設定）
        Cache\YSCacheStorage::flush_all();
    }

    /**
     * 取得設定值
     *
     * @param string $key 設定鍵.
     * @return mixed
     */
    public function get_setting( string $key ): mixed {
        return $this->settings[ $key ] ?? null;
    }

    /**
     * 取得所有設定
     *
     * @return array<string, mixed>
     */
    public function get_settings(): array {
        return $this->settings;
    }

    /**
     * 記錄日誌
     *
     * @param string $message 訊息.
     * @param string $level   等級.
     * @return void
     */
    public static function log( string $message, string $level = 'info' ): void {
        if ( ! self::instance()->settings['debug_mode'] ) {
            return;
        }
        Utils\YSLogger::log( $message, $level );
    }

    /**
     * 禁止複製
     */
    private function __clone() {}

    /**
     * 禁止反序列化
     *
     * @throws \Exception 禁止反序列化.
     */
    public function __wakeup(): void {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
