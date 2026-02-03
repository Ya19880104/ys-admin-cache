# YS Admin Cache

WordPress 後台頁面快取外掛，大幅提升後台載入速度。

## 功能特色

- **選擇性快取** - 自由選擇要快取的後台頁面
- **自訂快取時間** - 60 秒至 7 天，精確到秒
- **自動預載入** - 背景自動預載入快取頁面
- **智慧清除** - 內容更新時自動清除相關快取
- **檔案儲存** - 快取存放於檔案系統，不污染資料庫
- **即時統計** - 10 秒自動刷新的快取統計
- **莫蘭迪配色** - 優雅柔和的設定介面

## 系統需求

- WordPress 6.0+
- PHP 8.0+

## 安裝方式

1. 上傳外掛檔案到 `/wp-content/plugins/ys-admin-cache`
2. 在「外掛」選單中啟用外掛
3. 前往「設定 > YS Admin Cache」進行設定

## 外掛架構

```
ys-admin-cache/
├── ys-admin-cache.php           # 主外掛檔案
├── autoload.php                 # PSR-4 自動載入器
├── composer.json                # Composer 設定
├── uninstall.php                # 解安裝清理
│
├── src/
│   ├── YSAdminCache.php         # 主類別（單例模式）
│   │
│   ├── Cache/
│   │   ├── YSCacheManager.php   # 快取管理核心
│   │   ├── YSCacheStorage.php   # 檔案儲存層
│   │   └── YSCacheKey.php       # 快取鍵生成
│   │
│   ├── Admin/
│   │   └── YSSettingsPage.php   # 設定頁面（Settings API）
│   │
│   ├── Ajax/
│   │   ├── YSClearCacheHandler.php   # 清除快取 AJAX
│   │   └── YSPreloadHandler.php      # 預載入處理
│   │
│   ├── Hooks/
│   │   ├── YSOutputBuffer.php        # 輸出緩衝
│   │   └── YSCacheInvalidator.php    # 自動清除邏輯
│   │
│   └── Utils/
│       ├── YSCookieManager.php  # 安全 Cookie 管理
│       └── YSLogger.php         # 日誌記錄器
│
└── assets/
    ├── css/ys-admin-cache.css   # 莫蘭迪配色樣式
    └── js/ys-admin-cache.js     # 前端互動腳本
```

## 快取儲存

快取檔案存放於：
```
wp-content/uploads/cache/admin/
```

### 檔案格式
- 檔名：`{md5_hash}.cache`
- 內容：序列化的 PHP 陣列，包含：
  - `content` - 頁面 HTML 內容
  - `generated_at` - 生成時間
  - `user_id` - 使用者 ID
  - `expires` - 過期時間戳

### 安全保護
- `.htaccess` - 禁止直接存取
- `index.php` - 防止目錄列表

## 自動清除機制

以下事件會觸發相關快取清除：

| 事件 | 清除範圍 |
|------|----------|
| 新增/編輯/刪除文章 | edit.php 相關快取 |
| 啟用/停用外掛 | plugins.php |
| 切換佈景主題 | themes.php |
| 新增/編輯使用者 | users.php |
| 新增/審核留言 | edit-comments.php |
| 更新選單/小工具 | 全部快取 |

## Debug 日誌

啟用 Debug 模式後，日誌會寫入：
```
wp-content/uploads/wc-logs/ys-admin-cache-{date}-{hash}.log
```

日誌格式：
```
[2026-02-04 15:30:00] [INFO] Cache saved for page: edit.php, key: 1_abc123_def456
```

## 安全特性

- **Nonce 驗證** - 所有 AJAX 請求都有 CSRF 保護
- **權限檢查** - 使用 `current_user_can('manage_options')`
- **輸入清理** - 使用 `sanitize_*()` 函數
- **輸出逃脫** - 使用 `esc_*()` 函數
- **安全 Cookie** - HttpOnly、Secure、SameSite

## 排除頁面

以下頁面永遠不會被快取：
- 外掛自己的設定頁面 (`options-general.php?page=ys-admin-cache`)
- AJAX 請求
- REST API 請求
- Cron 請求

## 命名規範

| 類型 | 前綴 | 範例 |
|------|------|------|
| 類別 | `YS` | `YSAdminCache`, `YSCacheManager` |
| 命名空間 | `YangSheep\AdminCache\` | `YangSheep\AdminCache\Cache\` |
| Option | `ys_admin_cache_` | `ys_admin_cache_enabled` |
| Hook | `ys_admin_cache_` | `ys_admin_cache_cleared` |
| AJAX Action | `ys_admin_cache_` | `ys_admin_cache_clear` |

## 開發者

**YANGSHEEP DESIGN**
- 網站：https://yangsheep.com.tw

## 授權

GPL-2.0-or-later
