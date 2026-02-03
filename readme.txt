=== YS Admin Cache ===
Contributors: yangsheepdesign
Tags: admin cache, admin performance, admin speed, slow admin, woocommerce performance
Stable tag: 1.0.0
Requires PHP: 8.0
Requires at least: 6.0
Tested up to: 6.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress 後台頁面快取外掛，大幅提升後台載入速度。

== Description ==

YS Admin Cache 是一款輕量級的 WordPress 後台快取外掛，透過快取常用的後台頁面來加速管理介面的載入速度。

特別適合以下情況：
* 安裝了大量外掛的網站
* WooCommerce 商店（產品、訂單頁面）
* 後台載入緩慢的網站

= 主要功能 =

* **選擇性快取** - 自由選擇要快取的後台頁面
* **自動預載入** - 背景自動預載入快取頁面
* **智慧清除** - 內容更新時自動清除相關快取
* **安全設計** - 符合 WordPress 安全標準，修復已知 CVE 漏洞

= 安全特性 =

此外掛遵循 WordPress 安全最佳實踐：

* Nonce 驗證（CSRF 保護）
* 權限檢查（Capability-based）
* 輸入清理（Sanitization）
* 輸出逃脫（Escaping）
* 安全 Cookie 設定（HttpOnly、Secure、SameSite）

= 相容性 =

* PHP 8.0 - 8.4
* WordPress 6.0+
* WooCommerce（選配）

== Installation ==

= 最低需求 =

* WordPress 6.0 或更高版本
* PHP 8.0 或更高版本

= 安裝步驟 =

1. 上傳外掛檔案到 `/wp-content/plugins/ys-admin-cache` 目錄，或透過 WordPress 外掛畫面直接安裝
2. 在「外掛」選單中啟用外掛
3. 前往「設定 > YS Admin Cache」
4. 勾選「啟用快取」
5. 選擇要快取的頁面
6. 設定快取持續時間
7. 儲存設定

== Frequently Asked Questions ==

= 快取會自動清除嗎？ =

是的。當以下情況發生時，相關快取會自動清除：
* 新增、編輯或刪除文章
* 啟用或停用外掛
* 更新佈景主題
* 新增或編輯使用者
* 新增或審核留言

= 我可以手動清除快取嗎？ =

可以。前往「設定 > YS Admin Cache」，點擊「清除所有快取」按鈕即可。

= 這個外掛會影響前台效能嗎？ =

不會。此外掛僅在後台（wp-admin）運作，不會影響網站前台的效能。

= 支援 Multisite 嗎？ =

是的，支援 WordPress Multisite 安裝。

== Screenshots ==

1. 設定頁面 - 選擇要快取的頁面
2. 快取統計 - 查看目前快取狀態
3. 快取標籤 - 在快取頁面顯示標籤（選配）

== Changelog ==

= 1.0.0 =
* 首次發布
* PHP 8.0 - 8.4 完整支援
* 修復已知安全漏洞（XSS、不安全 Cookie）
* 實作 WordPress Security Trinity（Nonces、Capabilities、Data Validation）
* PSR-4 自動載入架構
* Settings API 整合
* 智慧快取失效機制

== Upgrade Notice ==

= 1.0.0 =
首次發布，建議所有使用舊版 WP Admin Cache 的用戶升級以獲得安全修復。
