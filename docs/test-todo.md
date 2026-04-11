# Test TODO

測試框架：PHPUnit 10 + Brain\Monkey（mock WordPress 函式）
測試類型：純單元測試，不需要 DB
測試位置：`tests/`

---

## Lihi_Service

### has_valid_token()
- [ ] cookie 不存在 → false
- [ ] cookie 為空字串 → false
- [ ] malformed token（缺少 `.` 分隔） → false
- [ ] payload 無 `exp` 欄位 → false
- [ ] exp 已過期（exp <= time()） → false
- [ ] exp 未來有效 → true

### login()
- [ ] client 正常回傳 token → 回傳 token string
- [ ] client 回傳空 token → 拋出 RuntimeException
- [ ] client 拋出例外 → 例外向上傳遞

### get_or_create_short_url()
- [ ] 已有相符 type_id 的短連結 → 直接回傳 `short_url`，不呼叫 create_site
- [ ] 無相符短連結 → 呼叫 create_site 並回傳新 `short_url`
- [ ] create_site 回傳空 `short_url` → 拋出 RuntimeException
- [ ] get_short_links 有多筆結果 → 回傳第一筆相符的 `short_url`
- [ ] create_site 的 body 包含正確的 permalink、type、type_id
- [ ] type 為 `attachment` → 使用 `wp_get_attachment_url()` 而非 `get_permalink()`

---

## Lihi_Client

### request() 核心邏輯
- [ ] GET 請求：$data 加到 query string，不加到 body
- [ ] POST 請求：$data 編碼為 JSON body
- [ ] auth=true：Header 包含 `Authorization: Bearer {token}`
- [ ] auth=false：Header 不包含 Authorization
- [ ] 回應碼 204 → 回傳空陣列
- [ ] body 為空字串 → 回傳空陣列
- [ ] body 為無效 JSON → 拋出 RuntimeException
- [ ] 回應碼 400+ → 拋出 RuntimeException（訊息含 status code）
- [ ] wp_remote_request 回傳 WP_Error → 拋出 RuntimeException

---

## AJAX Handler: lihi_copy_url

- [ ] post_id 為 0 → wp_send_json_error
- [ ] type 為空 → wp_send_json_error
- [ ] service 正常回傳 url → wp_send_json_success(['url' => ...])
- [ ] service 拋出例外 → wp_send_json_error(message)
