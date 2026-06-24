# Test TODO

測試框架：PHP 7.4 / PHP 8.2 PHPUnit containers + PHPUnit 9 + Brain\Monkey（mock WordPress 函式）+ WP_UnitTestCase（整合測試，需要 DB）
Composer 僅在官方 `php:*-cli` 測試 container 內執行；PHP 7.4 與 PHP 8.2 分別使用獨立 Composer file / lock file，只有 `lihi-short-url/`、`tests/`、`patchwork.json`、`phpunit.xml` 以唯讀方式掛到 `/app/code`，版本專屬 Composer file / lock 在 container 內映射成 `/app/composer.json` / `/app/composer.lock`，vendor directory 與 WordPress core install 存在 Docker named volumes，不寫入 repo 工作樹。
測試位置：`tests/`

CI / packaging：`.github/workflows/package-plugin.yml` 只在 tag push 時執行。Package job 會打包 `lihi-short-url/` 成 `build/lihi-short-url.zip`，驗證 ZIP 內含 `lihi-short-url/lihi-short-url.php` 與 `lihi-short-url/readme.txt`，並上傳 artifact `lihi-short-url-plugin`；release job 會下載同一個 artifact 建立或更新該 tag 的 GitHub Release，若 release 已存在則以 `--clobber` 替換 ZIP asset。
Release metadata：目前發版版本為 `1.0.3`；`lihi-short-url.php` header、WordPress.org `readme.txt` 的 `Stable tag` / changelog / upgrade notice / GitHub 維護 repo 連結、enqueue asset version、WordPress.org slug / text domain `lihi-short-url`、以及 `zh_TW` translation header 應保持一致。

| Test class | 基底 | 說明 |
|---|---|---|
| `ServiceTest` | `TestCase` + Brain\Monkey | 純單元，mock WordPress 函式 |
| `TokenStoreTest` | `TestCase` + Brain\Monkey | 純單元，mock transient/wp_cache |
| `ClientTest` | `TestCase` + Brain\Monkey | 純單元，mock `wp_remote_request` |
| `AuthClientTest` | `TestCase` + Brain\Monkey | 純單元，mock `wp_remote_request`，覆蓋 auth payload / Host header 邊界 |
| `AjaxCopyUrlTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式 |
| `AjaxUpdateEmailTest` | `TestCase` + Brain\Monkey | 純單元，mock AJAX 函式與 lihi client |
| `AdminNoticeTest` | `WP_UnitTestCase` | 整合，需要 DB；確認未設定 email 時不註冊 dashboard-wide setup notice |
| `HelperTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginHooksTest` | `WP_UnitTestCase` | 整合，需要 DB |
| `PluginLifecycleTest` | `WP_UnitTestCase` | 整合，需要 DB；確認停用 hook 清除 plugin-owned options / transient |
| `PluginLoadedTest` | `WP_UnitTestCase` | 整合，需要 DB |

---

## Helpers

- [x] `lihi_email()` — option 已設定 → 回傳 option 值
- [x] `lihi_email()` — option 為空字串 → 回傳空字串
- [x] `lihi_uuid()` — option 已設定 → 回傳 option 值
- [x] `lihi_uuid()` — option 未設定 → 產生 UUID v4 並保存到 `lihi_uuid`
- [x] `lihi_uuid()` — option 格式無效 → 重新產生 UUID v4 並替換 `lihi_uuid`
- [x] `lihi_config( $key )` — 載入 `includes/config.php` 並回傳對應 key 的值（由 `Lihi_Singletons::lihi_client()` 組裝 `Lihi_Client` 時驗證）
- [x] `lihi_config( $key )` — 未知 key 回傳 null（透過 `mockConfig()` 預設邏輯涵蓋）
- [x] `lihi_site_host()` — 取 `home_url()` host，供 auth payload 與 short-link type namespace 使用
- [x] `lihi_resolve_url()` — type=post/page → 使用 `get_permalink()`
- [x] `lihi_resolve_url()` — type=attachment → 使用 `wp_get_attachment_url()`
- [x] `lihi_resolve_url()` — 解析不到 URL → 拋出 RuntimeException
- [n/a] helper 分工 — `helper.php` 只保留 option/context helper functions；client / service / store 一律由 `Lihi_Singletons` static composition methods 取用（由程式碼審查保證）
- [n/a] UI hooks — email 空 → column/enqueue/attachment panel 未掛；email 已設時不再受 `lihi_domain` option 影響（是否註冊取決於 bootstrap 載入瞬間的 option 值，由程式碼審查保證）
- [x] bootstrap guard — email 空 → 不註冊 dashboard-wide `admin_notices`
- [x] bootstrap guard — email 已設 → 不註冊 dashboard-wide `admin_notices`

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
- [x] lihi client 正常回傳 token，且 service `login($email)` 傳入 email → 回傳 token string
- [x] lihi client 回傳空 token → 拋出 RuntimeException
- [x] lihi client 拋出例外 → 例外向上傳遞

### get_token()（透過 get_or_create_short_url 測試）
- [x] transient 有效 → 回傳 transient token，不呼叫 login
- [x] 搶到 lock，double-check 時 transient 已存在 → 回傳 transient token，不呼叫 login
- [x] transient 不存在，搶到 lock → 呼叫 login，存入 transient，回傳 token
- [x] 沒搶到 lock，poll 期間 transient 出現 → 回傳 transient token，不呼叫 login
- [x] 沒搶到 lock，poll 超時 → fallback 呼叫 login，存入 transient，回傳 token
- [x] login 拋出例外 → lock 釋放，例外向上傳遞

### get_profile()
- [x] 成功 → 回傳 `data` 子陣列（user_role / end_date）
- [x] `client->get_profile()` 拋出 `Lihi_Token_Invalid_Exception` → invalidate token、重新 login、再試一次
- [x] `lihi client->login($email)` 拋出 `Lihi_Auth_Exception`（email 未驗證）→ 向上傳遞
- [x] `lihi client->login($email)` 拋出 `Lihi_User_Invalid_Exception`（lihi 帳號不可使用）→ 向上傳遞

### get_url_options()
- [x] 成功 → 呼叫 `client->get_options($token)` 並回傳 `data` 子陣列（domains / utm_sources / utm_mediums）
- [x] `client->get_options()` 拋出 `Lihi_Token_Invalid_Exception` → invalidate token、重新 login、再試一次

### get_or_create_short_url()
- [x] `get_short_links()` 回傳 `data.site` 非空字串 → 直接回傳該短網址，不呼叫 create_site
- [x] `get_short_links()` 回傳 `data.site` 空字串 → 呼叫 create_site 並回傳新 `short_url`
- [x] create_site 回傳空 `short_url` → 拋出 RuntimeException
- [x] get_short_links 單一 find response → 使用 `data.site` 作為既有短網址
- [x] create_site 的 body 包含正確的 permalink、type、type_id
- [x] create_site body 的 `domain` 來自 modal 選擇；create AJAX 只要求非空值，最終 domain 有效性由 lihi API 判斷
- [x] create_site body 的 `tags` 是 comma-separated string：固定 `wordpress` / host / type，並與使用者輸入 tags 合併去重後用逗號串接
- [x] create_site body 有 UTM 時，目的 URL 加上 `utm_*` query string，且不另外傳 `utm` object
- [x] type 為 `attachment` → 使用 `wp_get_attachment_url()` 而非 `get_permalink()`
- [x] get_short_links 的 type 參數為 `"{type}:{host}"`（host 取自 `home_url()`）
- [x] create_site 的 body `type` 為 `"{type}:{host}"`，固定 tags 仍使用原始 `type`

### get_existing_short_url()
- [x] `data.site` 非空字串 → 回傳短網址，不呼叫 create_site
- [x] `data.site` 空字串 → 拋出 `Lihi_Not_Found_Exception`，不呼叫 create_site

### create_passthrough_nonce()
- [x] transient 有效 → 使用 cached token 呼叫 client，並回傳 `data.nonce`
- [x] client 回傳空 nonce → 拋出 RuntimeException
- [x] `client->create_passthrough_nonce()` 拋出 `Lihi_Token_Invalid_Exception` → invalidate token、重新 login、再試一次

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
- [x] get_profile() → GET /api/wordpress/v1/user/profile，帶 Authorization: Bearer
- [x] get_options() → GET /api/wordpress/v1/user/options，帶 Authorization: Bearer
- [x] create_passthrough_nonce() → POST /api/wordpress/v1/passthrough/nonce，body 帶 `{ challenge }` 並可帶 `{ target }`，且帶 Authorization: Bearer
- [x] get_sites() → GET /api/wordpress/v1/site/find，params 編為 query string
- [x] get_short_links() → GET /api/wordpress/v1/site/find，帶 type/type_id，陣列 type_id 轉為 comma-separated string
- [x] create_site() → POST /api/wordpress/v1/site/store，body 為 JSON
- [x] get_options() 回應碼 400 → 拋出 `Lihi_Validation_Exception`
- [x] get_options() 回傳 `result:false` → 拋出 `Lihi_Server_Exception`，不回傳空 options
- [x] get_sites() / get_short_links() 回應碼 400 → 拋出 `Lihi_Validation_Exception`
- [x] get_short_links() 回傳 JSON 5xx failure → 拋出 `Lihi_Server_Exception`，不誤判成短網址不存在
- [x] create_site() 回應碼 400 → 拋出 `Lihi_Validation_Exception`
- [x] create_passthrough_nonce() 回應碼 400 → 拋出 `Lihi_Validation_Exception`

> `Lihi_Client_Interface` 涵蓋 `wordpress/v1` 下外掛實際使用的 endpoints：
> auth（`login` / `update-email`）與 JWT `user/profile`、`user/options`、`passthrough/nonce`、`site/find`、`site/store`。`POST /mail` 外掛無用途，
> 不納入契約；site update/delete、`/posts`、`/site-urls` 不屬於本 API contract。

---

## Lihi_Client auth methods

已建立基本 payload / header 測試；剩餘錯誤映射仍待補齊：

- [x] `update_email()` → POST `/api/wordpress/v1/auth/update-email`，body 為 `{ email, password, hostname, uuid }`，且不覆寫 HTTP `Host` header
- [ ] `update_email()` 回應 200 `{ data: { verified: true } }` → 回傳 `['verified' => true]`
- [ ] `update_email()` 回應 200 `{ data: { verified: false } }` → 回傳 `['verified' => false]`
- [ ] `update_email()` 回應 400 → 拋出 `Lihi_Validation_Exception`
- [ ] `update_email()` 回應 429 → 拋出 `Lihi_Rate_Limit_Exception`
- [ ] `update_email()` 回應 500 → 拋出 `Lihi_Server_Exception`
- [ ] `update_email()` 回應非 JSON → 拋出 `Lihi_Server_Exception`
- [ ] `update_email()` `wp_remote_request` 回傳 `WP_Error` → 拋出 `Lihi_Server_Exception`
- [x] `login()` → POST `/api/wordpress/v1/auth/login`，body 為 `{ email, hostname, uuid, is_mobile }`，且不覆寫 HTTP `Host` header
- [ ] `login()` 回應 200 → 回傳 `['token' => ...]`
- [ ] `login()` 回應 400 → 拋出 `Lihi_Validation_Exception`
- [x] `login()` 回應 403 `email not verified` → 拋出 `Lihi_Auth_Exception`
- [x] `login()` 回應 403 `User Invalid` → 拋出 `Lihi_User_Invalid_Exception`
- [x] JWT endpoint 回應 403 `User Invalid` → 拋出 `Lihi_User_Invalid_Exception`
- [x] JWT endpoint 回應 404 `user_not_found ,please login again` → 拋出 `Lihi_User_Invalid_Exception`，service 會清 token 並直接顯示帳號不可使用訊息，不在同一 request 重試 login
- [x] JWT endpoint 回應 500 `Token invalid ,please login again` → 拋出 `Lihi_Token_Invalid_Exception`，service 會清 token 並重試 login
- [x] JWT endpoint 回應 500 `Token expired ,please login again` → 拋出 `Lihi_Token_Invalid_Exception`，service 會清 token 並重試 login
- [x] JWT endpoint 回應 500 `Something wrong ,please login again` → 拋出 `Lihi_Token_Invalid_Exception`，service 會清 token 並重試 login
- [ ] `login()` 回應 500 → 拋出 `Lihi_Server_Exception`

---

## AJAX Handlers: lihi_create_url / lihi_copy_url / lihi_edit_url

> 政策：建立、複製、編輯短網址都需要通過 nonce，且使用者必須對目標文章 / 媒體具備 `read_post` 權限；編輯短網址還需要 `manage_options`，因為它會產生 lihi-admin passthrough nonce。未登入請求仍由 `wp_ajax_lihi_create_url` / `wp_ajax_lihi_copy_url` / `wp_ajax_lihi_edit_url` action（未註冊 `wp_ajax_nopriv_*` 變體）擋下，無法抵達 handler。

- [x] AJAX parsing / validation / exception mapping / action registration 拆在 `includes/shorturl-column-ajax.php`，`includes/add-shorturl-column.php` 只保留 column UI hooks
- [x] email 為空 → wp_send_json_error「lihi email is not configured…」，HTTP 409
- [x] item_id 為 0 → wp_send_json_error，HTTP 400
- [x] `get_post_type( $item_id )` 無法解析 type → wp_send_json_error，HTTP 400
- [x] client payload 偽造 type → handler 忽略 payload，使用 `get_post_type( $item_id )` 的 server-side type 呼叫 service
- [x] `current_user_can( 'read_post', $item_id )` 拒絕 → wp_send_json_error，HTTP 403，不呼叫 service
- [x] `lihi_create_url` 的 domain / tags JSON array / UTM JSON object → sanitize 後傳入 service options
- [x] `lihi_create_url` 正常回傳 url → `update_post_meta($item_id, 'lihi_already', '1')` 並 `wp_send_json_success(['url' => ..., 'lihi_already' => true])`
- [x] `lihi_copy_url` → 呼叫 `get_existing_short_url()`，不呼叫 create flow；成功時仍回傳 url 並維持 `lihi_already = 1`
- [x] `lihi_copy_url` 且 upstream 短網址不存在 → `update_post_meta($item_id, 'lihi_already', '0')`，HTTP 410，payload code 為 `lihi_missing`
- [x] `lihi_edit_url` 無 `manage_options` 權限 → HTTP 403，不呼叫 service、不產生 passthrough nonce
- [x] `lihi_edit_url` → 先驗證 browser challenge、呼叫 `get_existing_short_url()`，再以短網址 target + challenge 呼叫 `create_passthrough_nonce()`，回傳 `nonce` / `form_action` / `target`
- [x] `lihi_edit_url` 缺少或傳入無效 browser challenge → HTTP 400，不呼叫 service
- [x] `lihi_edit_url` 且 upstream 短網址不存在 → `update_post_meta($item_id, 'lihi_already', '0')`，HTTP 410，payload code 為 `lihi_missing`
- [n/a] 前端 Copy 失敗只有 `code = lihi_missing` 才重設按鈕並開啟建立 modal；其他錯誤只顯示訊息、不改狀態（由程式碼審查 / JS 語法檢查保證）
- [n/a] 前端 Copy 狀態只在 `canEditShortUrl` 為 true 時渲染相鄰 Edit button；點擊 Edit 先顯示確認 modal，OK 後產生 verifier / challenge，取得 passthrough nonce，並用 hidden form POST `nonce` + `verifier` 到 lihi-admin redirect endpoint（由程式碼審查 / JS 語法檢查保證）
- [x] 建立 modal options → `wp_ajax_lihi_url_options` 從 options endpoint 回傳 domain `{ value, label }` options 與 UTM source / medium options
- [n/a] 前端建立 modal 的 Domain / UTM source / UTM medium 使用同一組 60 秒快取資料與 select loading / option rendering UI（由程式碼審查 / JS 語法檢查保證）
- [x] AJAX exception mapping 集中於 `handle_lihi_ajax_exception()`，create / copy / options handler 不重複維護相同 catch mapping
- [n/a] 前端 clipboard 被瀏覽器拒絕 → 已成功回傳的短網址直接以 prompt 顯示供手動複製，且按鈕狀態已先切為 `Copy`（由程式碼審查 / JS 語法檢查保證）
- [n/a] 前端 showNotice 使用可確認的共用 modal，支援 OK 後執行 callback；Copy missing 會先顯示錯誤，再由 callback 開啟建立 modal（由程式碼審查 / JS 語法檢查保證）
- [n/a] 前端 modal options 載入失敗 → 關閉 create modal 並顯示錯誤 modal，不會卡在 loading disabled 狀態（由程式碼審查 / JS 語法檢查保證）
- [n/a] 前端 Tags 欄位使用同一個 chip list：預設 tags 是不可移除 chip，input + Add 新增的使用者 tags 是可移除 chip（由程式碼審查 / JS 語法檢查保證）
- [x] service 拋出一般例外 → wp_send_json_error 友善訊息（不暴露內部細節），HTTP 500
- [x] service 拋出 `Lihi_Auth_Exception` → wp_send_json_error「email has not been verified」訊息（lihi API 回 403，表示 email 尚未驗證），HTTP 403
- [x] service 拋出 `Lihi_User_Invalid_Exception` → wp_send_json_error account unavailable 訊息（lihi API 回 `User Invalid` 或 `user_not_found ,please login again`，表示帳號不可使用），HTTP 403
- [x] service 拋出 `Lihi_Token_Invalid_Exception` → wp_send_json_error login session expired 訊息，HTTP 401
- [x] service 拋出 `Lihi_Validation_Exception` → wp_send_json_error「lihi API rejected…」訊息，HTTP 400
- [x] service 拋出 `Lihi_Server_Exception` → wp_send_json_error「lihi service is unavailable」訊息，HTTP 503

---

## AJAX Handler: lihi_update_email

- [x] 無 `manage_options` 權限 → wp_send_json_error，HTTP 403，不呼叫 `update_option`
- [x] email 為空 → 呼叫 `delete_option('lihi_email')`，wp_send_json_success 訊息含「cleared」，不呼叫 lihi client
- [x] email 全為空白字元 → 同上，視為清除
- [x] email 格式無效 → wp_send_json_error，HTTP 400，不呼叫 lihi client 與 `update_option`
- [x] email 被 `sanitize_email()` 清洗過後仍通不過 `is_email()`（例：`alice @example`）→ wp_send_json_error，HTTP 400，不呼叫 lihi client 與 `update_option`
- [x] lihi client 拋出 `Lihi_Validation_Exception` → wp_send_json_error「rejected the email」訊息，HTTP 400，`update_option` 不被呼叫
- [x] lihi client 拋出 `Lihi_Rate_Limit_Exception` → wp_send_json_error「Too many」訊息，HTTP 429，`update_option` 不被呼叫
- [x] lihi client 拋出 `Lihi_Server_Exception` → wp_send_json_error「unavailable」訊息，HTTP 503，`update_option` 不被呼叫
- [x] lihi client 回傳 `verified: true` → 呼叫 `update_option('lihi_email', …)`，wp_send_json_success(['verified' => true])
- [x] lihi client 回傳 `verified: false` → 呼叫 `update_option('lihi_email', …)`，wp_send_json_success(['verified' => false])

---

## Settings page rendering

- [n/a] `render_settings_page()` 只在成功載入 profile 時輸出 `<div id="lihi-account-section">`，錯誤通知則留在表單 messages 區塊（前端 JS 依靠這個 id 在 email 更新成功時清空舊帳號資料；由程式碼審查保證）
- [n/a] `lihi-settings.js` email 存檔成功時，先清空 `#lihi-account-section`，verified 時延遲 2 秒再 reload（避免「驗證信已寄出」時舊帳號的 role / end_date 殘留；2 秒延遲讓 admin 來得及讀到「✓ Email verified」訊息；由程式碼審查保證）

---

## Plugin hooks (整合)

- [n/a] release metadata 1.0.3 — plugin header、readme Stable tag / changelog / upgrade notice / GitHub 維護 repo 連結、enqueue asset version、WordPress.org slug / text domain `lihi-short-url`、translation header 同步（由程式碼審查保證）
- [x] `wp_ajax_lihi_copy_url` 已註冊
- [x] `wp_ajax_lihi_create_url` 已註冊
- [x] `wp_ajax_lihi_edit_url` 已註冊
- [x] `wp_ajax_lihi_url_options` 已註冊
- [x] `wp_ajax_lihi_update_email` 已註冊
- [x] `register_deactivation_hook()` 已註冊停用清理 callback
- [x] deactivation cleanup — 清除 `lihi_email` / `lihi_domain` / `lihi_uuid` / `lihi_uuid_lock` options 與 `lihi_token` transient
- [x] `admin_enqueue_scripts` 白名單（edit/upload/post/post-new）→ enqueue `lihi-button-api` / `lihi-button-modal` / `lihi-button`，且 main script 依賴前兩者
- [x] `admin_enqueue_scripts` 非白名單 → 不 enqueue
- [x] `manage_post_posts_columns` 有 `lihi` 欄位
- [x] `manage_post_posts_custom_column` 輸出空的 `data-lihi-container`，含 `data-id` / `data-type="post"` / `data-lihi-already`；實際 button 由前端 JS 放入 container
- [x] `manage_post_posts_custom_column` 在 post meta `lihi_already = 1` 時輸出 `data-lihi-already="1"`
- [x] `manage_media_columns` 有 `lihi` 欄位
- [x] `manage_media_custom_column` 輸出空的 button container，含 `data-type="attachment"`
- [x] `attachment_fields_to_edit` 新增 `lihi` 欄位含空的 button container，並在 attachment meta `lihi_already = 1` 時輸出 `data-lihi-already="1"`
- [x] `admin_init` 註冊 `lihi_email` setting
- [x] `admin_menu` 註冊 Settings → lihi Short URL 頁面
- [x] `update_option('lihi_email', …)` → `lihi_token` transient 被清除
