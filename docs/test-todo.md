# Test TODO

測試框架：PHP 7.4 / PHP 8.2 PHPUnit containers + PHPUnit 9 + Brain\Monkey（mock WordPress 函式）+ WP_UnitTestCase（整合測試，需要 DB）
Composer 僅在官方 `php:*-cli` 測試 container 內執行；PHP 7.4 與 PHP 8.2 分別使用獨立 Composer file / lock file，只有 `lihi-short-url/`、`tests/`、`patchwork.json`、`phpunit.xml` 以唯讀方式掛到 `/app/code`，版本專屬 Composer file / lock 在 container 內映射成 `/app/composer.json` / `/app/composer.lock`，vendor directory 與 WordPress core install 存在 Docker named volumes，不寫入 repo 工作樹。
測試位置：`tests/`

CI / packaging：`.github/workflows/package-plugin.yml` 只在 tag push 時執行。Package job 會打包 `lihi-short-url/` 成 `build/lihi-short-url.zip`，驗證 ZIP 內含 `lihi-short-url/lihi-short-url.php` 與 `lihi-short-url/readme.txt`，並上傳 artifact `lihi-short-url-plugin`；release job 會下載同一個 artifact 建立或更新該 tag 的 GitHub Release，若 release 已存在則以 `--clobber` 替換 ZIP asset。
Release metadata：目前發版版本為 `1.0.2`；`lihi-short-url.php` header、WordPress.org `readme.txt` 的 `Stable tag` / changelog / upgrade notice / GitHub 維護 repo 連結、enqueue asset version、WordPress.org slug / text domain `lihi-short-url`、以及 `zh_TW` translation header 應保持一致。

| Test class | 基底 | 說明 |
|---|---|---|
| `ServiceTest` | `TestCase` + Brain\Monkey | 純單元，mock WordPress 函式 |
| `TokenStoreTest` | `TestCase` + Brain\Monkey | 純單元，mock transient/wp_cache |
| `ClientTest` | `TestCase` + Brain\Monkey | 純單元，mock `wp_remote_request` |
| `AuthClientTest` | `TestCase` + Brain\Monkey | 純單元，mock `wp_remote_request`（尚未撰寫） |
| `AjaxCopyUrlTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式 |
| `AjaxUpdateEmailTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式與 auth client |
| `AjaxUpdateDomainTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式 |
| `AdminNoticeTest` | `WP_UnitTestCase` | 整合，需要 DB；確認未設定 email/domain 時不註冊 dashboard-wide setup notice |
| `HelperTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginHooksTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginLoadedTest` | `WP_UnitTestCase` | 整合，需要 DB |

---

## Helpers

- [x] `lihi_email()` — option 已設定 → 回傳 option 值
- [x] `lihi_email()` — option 為空字串 → 回傳空字串
- [x] `lihi_domain()` — option 已設定 → 回傳 option 值
- [x] `lihi_domain()` — option 為空字串 → 回傳空字串
- [x] `lihi_uuid()` — option 已設定 → 回傳 option 值
- [x] `lihi_uuid()` — option 未設定 → 產生 UUID v4 並保存到 `lihi_uuid`
- [x] `lihi_uuid()` — option 格式無效 → 重新產生 UUID v4 並替換 `lihi_uuid`
- [x] `lihi_config( $key )` — 載入 `includes/config.php` 並回傳對應 key 的值（由 `Lihi_Client` 建構子與 `Lihi_Service` 透過實際呼叫驗證）
- [x] `lihi_config( $key )` — 未知 key 回傳 null（透過 `mockConfig()` 預設邏輯涵蓋）
- [n/a] UI hooks — email 或 domain 任一空 → column/enqueue/attachment panel 未掛（是否註冊取決於 bootstrap 載入瞬間的 option 值，由程式碼審查保證）
- [x] bootstrap guard — email 空 → 不註冊 dashboard-wide `admin_notices`
- [x] bootstrap guard — email 已設、domain 空 → 不註冊 dashboard-wide `admin_notices`
- [x] bootstrap guard — email 與 domain 都已設 → 不註冊 dashboard-wide `admin_notices`

---

## Lihi_Uuid_Store

- [x] `get()` → option 已設定且為 UUID v4 → 回傳既有 `lihi_uuid`
- [x] `get()` → option 未設定且取得 option-backed `lihi_uuid_lock` → 產生 UUID v4 並以 `autoload = no` 保存到 `lihi_uuid`，讀回 persisted UUID，最後釋放 lock
- [x] `get()` → `add_option()` 競態失敗 → 重新讀取並回傳已保存的 valid UUID
- [x] `get()` → option 未設定且寫入後讀不到 valid UUID → 拋出 `Lihi_Server_Exception`
- [x] `get()` → option 格式無效且取得 option-backed `lihi_uuid_lock` → 重新產生 UUID v4 並替換 `lihi_uuid`，讀回 persisted UUID，最後釋放 lock
- [x] `get()` → option 格式無效且寫入後讀不到 valid UUID → 拋出 `Lihi_Server_Exception`
- [x] `get()` → lock 已被其他 request 取得 → 輪詢等到既有 UUID 後直接回傳，不重複產生
- [x] `get()` → lock 一直未釋放且 UUID 仍不存在 → 拋出 `Lihi_Server_Exception`，不產生未保存 UUID

---

## Lihi_Token_Store

- [x] `get()` → `get_transient('lihi_token')`
- [x] `get()` miss → false
- [x] `set()` → `set_transient('lihi_token', $token, DAY_IN_SECONDS)`
- [x] `delete()` → `delete_transient('lihi_token')`
- [x] `acquire_lock()` → `wp_cache_add('lihi_token_lock', 1, 'transient', 30)`
- [x] `release_lock()` → `wp_cache_delete('lihi_token_lock', 'transient')`
- [x] `flush()` → acquire → delete → release（順序）
- [x] `flush()` — lock 被佔用 → poll 到釋放再 delete
- [x] `flush()` — poll 超時 → 仍執行 delete

---

## Lihi_Service

### login()
- [x] auth client 正常回傳 token → 回傳 token string
- [x] auth client 回傳空 token → 拋出 RuntimeException
- [x] auth client 拋出例外 → 例外向上傳遞

### get_token()（透過 get_or_create_short_url 測試）
- [x] transient 有效 → 回傳 transient token，不呼叫 login
- [x] 搶到 lock，double-check 時 transient 已存在 → 回傳 transient token，不呼叫 login
- [x] transient 不存在，搶到 lock → 呼叫 login，存入 transient，回傳 token
- [x] 沒搶到 lock，poll 期間 transient 出現 → 回傳 transient token，不呼叫 login
- [x] 沒搶到 lock，poll 超時 → fallback 呼叫 login，存入 transient，回傳 token
- [x] login 拋出例外 → lock 釋放，例外向上傳遞

### get_profile()
- [x] 成功 → 回傳 `data` 子陣列（user_role / end_date / domains）
- [x] `client->get_profile()` 拋出 `Lihi_Token_Invalid_Exception` → invalidate token、重新 login、再試一次
- [x] `auth_client->login()` 拋出 `Lihi_Auth_Exception`（email 未驗證）→ 向上傳遞

### get_or_create_short_url()
- [x] 已有相符 type_id 的短連結 → 直接回傳 `short_url`，不呼叫 create_site
- [x] 無相符短連結 → 呼叫 create_site 並回傳新 `short_url`
- [x] create_site 回傳空 `short_url` → 拋出 RuntimeException
- [x] get_short_links 有多筆結果 → 回傳第一筆相符的 `short_url`
- [x] create_site 的 body 包含正確的 permalink、type、type_id
- [x] `lihi_domain` option 已設 → create_site body 的 `domain` 為該值
- [x] `lihi_domain` option 未設 → create_site body 的 `domain` 為空字串（`redirect_domain` config key 已棄用，改由 wp_option 決定）
- [x] type 為 `attachment` → 使用 `wp_get_attachment_url()` 而非 `get_permalink()`
- [x] get_short_links 的 type 參數為 `"{type}:{host}"`（host 取自 `home_url()`）
- [x] create_site 的 body `type` 為 `"{type}:{host}"`，`tags` 仍使用原始 `type`

### resolve_url()
- [x] type=post → 使用 `get_permalink()`
- [x] type=page → 使用 `get_permalink()`
- [x] type=attachment → 使用 `wp_get_attachment_url()`
- [x] `wp_get_attachment_url()` 回傳 false → 拋出 RuntimeException
- [x] `get_permalink()` 回傳 false → 拋出 RuntimeException

---

## Lihi_Client

### request() 核心邏輯
- [x] GET 請求：$data 加到 query string，不加到 body
- [x] POST 請求：$data 編碼為 JSON body
- [x] token 非空：Header 包含 `Authorization: Bearer {token}`
- [x] 回應碼 204 → 回傳空陣列
- [x] body 為空字串 → 回傳空陣列
- [x] body 為無效 JSON → 拋出 RuntimeException
- [x] 回應碼 400+ → 拋出 RuntimeException（訊息含 status code）
- [x] wp_remote_request 回傳 WP_Error → 拋出 RuntimeException

### 各方法路徑與 HTTP method
- [x] get_profile() → GET /api/wordpress/v1/profile，帶 Authorization: Bearer
- [x] get_sites() → GET /api/wordpress/v1/sites，params 編為 query string
- [x] get_short_links() → GET /api/wordpress/v1/sites，帶 type/type_id/per_page
- [x] create_site() → POST /api/wordpress/v1/sites，body 為 JSON
- [x] create_site() 回應碼 400 → 拋出 `Lihi_Validation_Exception`

> `Lihi_Client_Interface` 只涵蓋 `wordpress/v1` 下**非 auth 的 jwt endpoints**：
> `profile`、`sites`（index/store）。Auth（`login` / `update-email`）屬於另一個服務
> （lihi Auth API），由 `Lihi_Auth_Client_Interface` 負責。`POST /auth/mail` 外掛無用途，
> 不納入契約；`PUT/PATCH/DELETE /sites`、`/posts`、`/site-urls` 不屬於本 API contract。

---

## Lihi_Auth_Client

尚未建立測試。涵蓋範圍應包含：

- [x] `update_email()` → POST `/auth/update-email`，body 為 `{ email, hostname, uuid }`，且不覆寫 HTTP `Host` header
- [ ] `update_email()` 回應 200 `{ data: { verified: true } }` → 回傳 `['verified' => true]`
- [ ] `update_email()` 回應 200 `{ data: { verified: false } }` → 回傳 `['verified' => false]`
- [ ] `update_email()` 回應 400 → 拋出 `Lihi_Validation_Exception`
- [ ] `update_email()` 回應 429 → 拋出 `Lihi_Rate_Limit_Exception`
- [ ] `update_email()` 回應 500 → 拋出 `Lihi_Server_Exception`
- [ ] `update_email()` 回應非 JSON → 拋出 `Lihi_Server_Exception`
- [ ] `update_email()` `wp_remote_request` 回傳 `WP_Error` → 拋出 `Lihi_Server_Exception`
- [x] `login()` → POST `/auth/login`，body 為 `{ email, hostname, uuid, is_mobile }`，且不覆寫 HTTP `Host` header
- [ ] `login()` 回應 200 → 回傳 `['token' => ...]`
- [ ] `login()` 回應 400 → 拋出 `Lihi_Validation_Exception`
- [ ] `login()` 回應 403 → 拋出 `Lihi_Auth_Exception`
- [ ] `login()` 回應 500 → 拋出 `Lihi_Server_Exception`

---

## AJAX Handler: lihi_copy_url

> 政策：產生短網址需要通過 nonce，且使用者必須對目標文章 / 媒體具備 `read_post` 權限。未登入請求仍由 `wp_ajax_lihi_copy_url` action（未註冊 `wp_ajax_nopriv_*` 變體）擋下，無法抵達此 handler。

- [x] email 為空 → wp_send_json_error「lihi email is not configured…」，HTTP 409
- [x] domain 為空（email 已設）→ wp_send_json_error「lihi redirect domain is not configured…」，HTTP 409
- [x] item_id 為 0 → wp_send_json_error，HTTP 400
- [x] `get_post_type( $item_id )` 無法解析 type → wp_send_json_error，HTTP 400
- [x] client payload 偽造 type → handler 忽略 payload，使用 `get_post_type( $item_id )` 的 server-side type 呼叫 service
- [x] `current_user_can( 'read_post', $item_id )` 拒絕 → wp_send_json_error，HTTP 403，不呼叫 service
- [x] service 正常回傳 url → wp_send_json_success(['url' => ...])
- [x] service 拋出一般例外 → wp_send_json_error 友善訊息（不暴露內部細節），HTTP 500
- [x] service 拋出 `Lihi_Auth_Exception` → wp_send_json_error「email has not been verified」訊息（auth service 回 403，表示 email 尚未驗證），HTTP 403
- [x] service 拋出 `Lihi_Validation_Exception` → wp_send_json_error「lihi API rejected…」訊息，HTTP 400
- [x] service 拋出 `Lihi_Server_Exception` → wp_send_json_error「lihi service is unavailable」訊息，HTTP 503

---

## AJAX Handler: lihi_update_domain

- [x] 無 `manage_options` 權限 → wp_send_json_error，HTTP 403，不呼叫 update_option / delete_option
- [x] domain 為空 → 呼叫 `delete_option('lihi_domain')`，wp_send_json_success 訊息含「cleared」
- [x] domain 全為空白字元 → 同上，視為清除
- [x] domain 格式無效（非 hostname 樣式）→ wp_send_json_error「Invalid」，HTTP 400，不呼叫 update_option / delete_option
- [x] domain 格式合法 → 呼叫 `update_option('lihi_domain', …)`，wp_send_json_success 訊息含「saved」

---

## AJAX Handler: lihi_update_email

- [x] 無 `manage_options` 權限 → wp_send_json_error，HTTP 403，不呼叫 `update_option`
- [x] email 為空 → 呼叫 `delete_option('lihi_email')`，wp_send_json_success 訊息含「cleared」，不呼叫 auth client
- [x] email 全為空白字元 → 同上，視為清除
- [x] email 格式無效 → wp_send_json_error，HTTP 400，不呼叫 auth client 與 `update_option`
- [x] email 被 `sanitize_email()` 清洗過後仍通不過 `is_email()`（例：`alice @example`）→ wp_send_json_error，HTTP 400，不呼叫 auth client 與 `update_option`
- [x] auth client 拋出 `Lihi_Validation_Exception` → wp_send_json_error「rejected the email」訊息，HTTP 400，`update_option` 不被呼叫
- [x] auth client 拋出 `Lihi_Rate_Limit_Exception` → wp_send_json_error「Too many」訊息，HTTP 429，`update_option` 不被呼叫
- [x] auth client 拋出 `Lihi_Server_Exception` → wp_send_json_error「unavailable」訊息，HTTP 503，`update_option` 不被呼叫
- [x] auth client 回傳 `verified: true` → 呼叫 `update_option('lihi_email', …)`，wp_send_json_success(['verified' => true])
- [x] auth client 回傳 `verified: false` → 呼叫 `update_option('lihi_email', …)`，wp_send_json_success(['verified' => false])

---

## Settings page rendering

- [n/a] `render_settings_page()` 把 profile / 錯誤通知區塊包在 `<div id="lihi-account-section">` 內（前端 JS 依靠這個 id 在 email 更新成功時清空舊帳號資料；由程式碼審查保證）
- [n/a] `lihi-settings.js` email 存檔成功時，先清空 `#lihi-account-section`，verified 時延遲 2 秒再 reload（避免「驗證信已寄出」時舊帳號的 role / end_date / domain selector 殘留；2 秒延遲讓 admin 來得及讀到「✓ Email verified」訊息；由程式碼審查保證）

---

## Plugin hooks (整合)

- [n/a] release metadata 1.0.2 — plugin header、readme Stable tag / changelog / upgrade notice / GitHub 維護 repo 連結、enqueue asset version、WordPress.org slug / text domain `lihi-short-url`、translation header 同步（由程式碼審查保證）
- [x] `wp_ajax_lihi_copy_url` 已註冊
- [x] `wp_ajax_lihi_update_email` 已註冊
- [x] `admin_enqueue_scripts` 白名單（edit/upload/post/post-new）→ enqueue lihi-button
- [x] `admin_enqueue_scripts` 非白名單 → 不 enqueue
- [x] `manage_post_posts_columns` 有 `lihi` 欄位
- [x] `manage_post_posts_custom_column` 輸出含 `data-lihi` / `data-id` / `data-type="post"` 按鈕
- [x] `manage_media_columns` 有 `lihi` 欄位
- [x] `manage_media_custom_column` 輸出 `data-type="attachment"` 按鈕
- [x] `attachment_fields_to_edit` 新增 `lihi` 欄位含按鈕
- [x] `admin_init` 註冊 `lihi_email` setting
- [x] `admin_menu` 註冊 Settings → lihi Short URL 頁面
- [x] `update_option('lihi_email', …)` → `lihi_token` transient 被清除
- [x] `update_option('lihi_email', …)` → `lihi_domain` option 被清除
- [x] `delete_option('lihi_email')` → `lihi_domain` option 被清除
