# Test TODO

測試框架：PHPUnit 10 + Brain\Monkey（mock WordPress 函式）
測試類型：純單元測試，不需要 DB
測試位置：`tests/`

---

## Settings / lihi_email helper

- [ ] `lihi_email()` — option 已設定 → 回傳 option 值
- [ ] `lihi_email()` — option 為空字串 → 回傳空字串
- [ ] bootstrap guard — email 空 → 後續 client/service 檔案未載入（`Lihi_Client_Interface` 不存在）
- [ ] bootstrap guard — email 空 → 註冊 `admin_notices` action

---

## Lihi_Service

### has_valid_token()
- [x] cookie 不存在 → false
- [x] cookie 為空字串 → false
- [x] malformed token（缺少 `.` 分隔） → false
- [x] payload 無 `exp` 欄位 → false
- [x] exp 已過期（exp <= time()） → false
- [x] exp 未來有效 → true

### login()
- [x] client 正常回傳 token → 回傳 token string
- [x] client 回傳空 token → 拋出 RuntimeException
- [x] client 拋出例外 → 例外向上傳遞

### get_token()（透過 get_or_create_short_url 測試）
- [x] cookie 有效 → 直接回傳 cookie token，不呼叫 login
- [x] cookie 不存在，transient 有效 → 回傳 transient token，不呼叫 login
- [x] cookie 已過期，transient 有效 → 回傳 transient token，不呼叫 login
- [x] 搶到 lock，double-check 時 transient 已存在 → 回傳 transient token，不呼叫 login
- [x] cookie 不存在，transient 不存在，搶到 lock → 呼叫 login，存入 transient + cookie，回傳 token
- [x] 沒搶到 lock，poll 期間 transient 出現 → 回傳 transient token，不呼叫 login
- [x] 沒搶到 lock，poll 超時 → fallback 呼叫 login，存入 transient + cookie，回傳 token
- [x] login 拋出例外 → lock 釋放，例外向上傳遞

### get_or_create_short_url()
- [x] 已有相符 type_id 的短連結 → 直接回傳 `short_url`，不呼叫 create_site
- [x] 無相符短連結 → 呼叫 create_site 並回傳新 `short_url`
- [x] create_site 回傳空 `short_url` → 拋出 RuntimeException
- [x] get_short_links 有多筆結果 → 回傳第一筆相符的 `short_url`
- [x] create_site 的 body 包含正確的 permalink、type、type_id
- [x] type 為 `attachment` → 使用 `wp_get_attachment_url()` 而非 `get_permalink()`

### resolve_url()
- [x] type=post → 使用 `get_permalink()`
- [x] type=page → 使用 `get_permalink()`
- [x] type=attachment → 使用 `wp_get_attachment_url()`

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
- [x] wp_remote_request 回傳 WP_Error → 拋出 RuntimeException

### 各方法路徑與 HTTP method
- [x] get_posts() → GET /api/wordpress/v1/posts，locale 傳為 query param
- [x] get_short_links() → GET /api/wordpress/v1/sites，帶 type/type_id/per_page
- [x] update_site() → PUT /api/wordpress/v1/sites/{id}
- [x] delete_site() → DELETE /api/wordpress/v1/sites/{id}，回傳 true
- [x] create_site_url() → POST /api/wordpress/v1/site-urls
- [x] update_site_url() → PUT /api/wordpress/v1/site-urls/{id}
- [x] delete_site_url() → DELETE /api/wordpress/v1/site-urls/{id}，回傳 true

---

## AJAX Handler: lihi_copy_url

- [x] item_id 為 0 → wp_send_json_error
- [x] type 為空 → wp_send_json_error
- [x] service 正常回傳 url → wp_send_json_success(['url' => ...])
- [x] service 拋出例外 → wp_send_json_error(message)
