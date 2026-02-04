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

        // Cron 預載入（始終初始化以處理請求）
        Cron\YSCronPreloader::init();

        // 如果啟用預載入，確保 Cron 已排程
        if ( $this->settings['enabled'] && $this->settings['preload_enabled'] ) {
            $this->maybe_schedule_cron();
        }

        // Ajax 處理（保留 JS 預載入作為備用）
        Ajax\YSPreloadHandler::init();
        Ajax\YSClearCacheHandler::init();

        // 設定變更時重新安排 Cron
        add_action( 'update_option_ys_admin_cache_preload', [ $this, 'on_preload_setting_changed' ], 10, 2 );
        add_action( 'update_option_ys_admin_cache_duration', [ $this, 'on_duration_setting_changed' ], 10, 2 );
        add_action( 'update_option_ys_admin_cache_enabled', [ $this, 'on_enabled_setting_changed' ], 10, 2 );
    }

    /**
     * 如果尚未排程或已過期，安排 Cron
     *
     * @return void
     */
    private function maybe_schedule_cron(): void {
        $next_run = Cron\YSCronPreloader::get_next_run();

        // 未排程或已過期超過 60 秒，重新排程
        if ( ! $next_run || ( $next_run - time() ) < -60 ) {
            Cron\YSCronPreloader::schedule( $this->settings['duration'] );
        }
    }

    /**
     * 預載入設定變更時
     *
     * @param mixed $old_value 舊值.
     * @param mixed $new_value 新值.
     * @return void
     */
    public function on_preload_setting_changed( $old_value, $new_value ): void {
        if ( 'yes' === $new_value && get_option( 'ys_admin_cache_enabled', 'yes' ) === 'yes' ) {
            // 啟用預載入
            $duration = absint( get_option( 'ys_admin_cache_duration', 300 ) );
            Cron\YSCronPreloader::schedule( $duration );
        } else {
            // 停用預載入
            Cron\YSCronPreloader::unschedule();
        }
    }

    /**
     * 快取時間設定變更時
     *
     * @param mixed $old_value 舊值.
     * @param mixed $new_value 新值.
     * @return void
     */
    public function on_duration_setting_changed( $old_value, $new_value ): void {
        if ( get_option( 'ys_admin_cache_preload', 'yes' ) === 'yes' &&
             get_option( 'ys_admin_cache_enabled', 'yes' ) === 'yes' ) {
            // 重新安排 Cron
            Cron\YSCronPreloader::schedule( absint( $new_value ) );
        }
    }

    /**
     * 啟用設定變更時
     *
     * @param mixed $old_value 舊值.
     * @param mixed $new_value 新值.
     * @return void
     */
    public function on_enabled_setting_changed( $old_value, $new_value ): void {
        if ( 'yes' !== $new_value ) {
            // 停用時取消 Cron
            Cron\YSCronPreloader::unschedule();
        } elseif ( get_option( 'ys_admin_cache_preload', 'yes' ) === 'yes' ) {
            // 啟用時安排 Cron
            $duration = absint( get_option( 'ys_admin_cache_duration', 300 ) );
            Cron\YSCronPreloader::schedule( $duration );
        }
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
        // 取消 Cron 排程
        Cron\YSCronPreloader::unschedule();

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
