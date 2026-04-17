# Test TODO

測試框架：PHPUnit 9 + Brain\Monkey（mock WordPress 函式）+ WP_UnitTestCase（整合測試，需要 DB）
測試位置：`tests/`

| Test class | 基底 | 說明 |
|---|---|---|
| `ServiceTest` | `TestCase` + Brain\Monkey | 純單元，mock WordPress 函式 |
| `TokenStoreTest` | `TestCase` + Brain\Monkey | 純單元，mock transient/wp_cache |
| `ClientTest` | `TestCase` + Brain\Monkey | 純單元，mock `wp_remote_request` |
| `AjaxCopyUrlTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式 |
| `AdminNoticeTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `HelperTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginHooksTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginLoadedTest` | `WP_UnitTestCase` | 整合，需要 DB |

---

## Settings / lihi_email helper

- [x] `lihi_email()` — option 已設定 → 回傳 option 值
- [x] `lihi_email()` — option 為空字串 → 回傳空字串
- [n/a] UI hooks — email 空 → column/enqueue/attachment panel 未掛（是否註冊取決於 bootstrap 載入瞬間的 email 值，由程式碼審查保證）
- [x] bootstrap guard — email 空 → 註冊 `admin_notices` action
- [x] admin notice — 有 `manage_options` 權限 → 輸出含設定頁連結的 warning notice
- [x] admin notice — 無 `manage_options` 權限 → 無輸出

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
- [x] client 正常回傳 token → 回傳 token string
- [x] client 回傳空 token → 拋出 RuntimeException
- [x] client 拋出例外 → 例外向上傳遞

### get_token()（透過 get_or_create_short_url 測試）
- [x] transient 有效 → 回傳 transient token，不呼叫 login
- [x] 搶到 lock，double-check 時 transient 已存在 → 回傳 transient token，不呼叫 login
- [x] transient 不存在，搶到 lock → 呼叫 login，存入 transient，回傳 token
- [x] 沒搶到 lock，poll 期間 transient 出現 → 回傳 transient token，不呼叫 login
- [x] 沒搶到 lock，poll 超時 → fallback 呼叫 login，存入 transient，回傳 token
- [x] login 拋出例外 → lock 釋放，例外向上傳遞

### get_or_create_short_url()
- [x] 已有相符 type_id 的短連結 → 直接回傳 `short_url`，不呼叫 create_site
- [x] 無相符短連結 → 呼叫 create_site 並回傳新 `short_url`
- [x] create_site 回傳空 `short_url` → 拋出 RuntimeException
- [x] get_short_links 有多筆結果 → 回傳第一筆相符的 `short_url`
- [x] create_site 的 body 包含正確的 permalink、type、type_id
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
- [x] token 為空（login）：Header 不包含 Authorization
- [x] 回應碼 204 → 回傳空陣列
- [x] body 為空字串 → 回傳空陣列
- [x] body 為無效 JSON → 拋出 RuntimeException
- [x] 回應碼 400+ → 拋出 RuntimeException（訊息含 status code）
- [x] login() 回應碼 400 且 msg 含 `email` key → 拋出 `Lihi_Email_Exception`
- [x] login() 回應碼 400 但 msg 無 `email` key → 拋出 `Lihi_Auth_Exception`（api_key 問題）
- [x] wp_remote_request 回傳 WP_Error → 拋出 RuntimeException

### 各方法路徑與 HTTP method
- [x] get_posts() → GET /api/wordpress/v1/posts，locale 傳為 query param
- [x] get_short_links() → GET /api/wordpress/v1/sites，帶 type/type_id/per_page
- [x] update_site() → PUT /api/wordpress/v1/sites/{id}
- [x] delete_site() → DELETE /api/wordpress/v1/sites/{id}，回傳 true
- [x] create_site_url() → POST /api/wordpress/v1/site-urls
- [x] create_site_url() 回應碼 400 → 拋出 `Lihi_Validation_Exception`
- [x] update_site_url() → PUT /api/wordpress/v1/site-urls/{id}
- [x] delete_site_url() → DELETE /api/wordpress/v1/site-urls/{id}，回傳 true

---

## AJAX Handler: lihi_copy_url

- [x] email 為空 → wp_send_json_error「Lihi email is not configured…」
- [x] item_id 為 0 → wp_send_json_error
- [x] type 為空 → wp_send_json_error
- [x] service 正常回傳 url → wp_send_json_success(['url' => ...])
- [x] service 拋出一般例外 → wp_send_json_error 友善訊息（不暴露內部細節）
- [x] service 拋出 `Lihi_Auth_Exception` → wp_send_json_error「plugin version is no longer supported」訊息（api_key 被拒，表示外掛版本過舊）

---

## Plugin hooks (整合)

- [x] `wp_ajax_lihi_copy_url` 已註冊
- [x] `admin_enqueue_scripts` 白名單（edit/upload/post/post-new）→ enqueue lihi-button
- [x] `admin_enqueue_scripts` 非白名單 → 不 enqueue
- [x] `manage_post_posts_columns` 有 `lihi` 欄位
- [x] `manage_post_posts_custom_column` 輸出含 `data-lihi` / `data-id` / `data-type="post"` 按鈕
- [x] `manage_media_columns` 有 `lihi` 欄位
- [x] `manage_media_custom_column` 輸出 `data-type="attachment"` 按鈕
- [x] `attachment_fields_to_edit` 新增 `lihi` 欄位含按鈕
- [x] `admin_init` 註冊 `lihi_email` setting
- [x] `admin_menu` 註冊 Settings → Lihi Short URL 頁面
- [x] `update_option('lihi_email', …)` → `lihi_token` transient 被清除
