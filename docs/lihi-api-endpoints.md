# lihi Wordpress API Endpoints

本外掛目前對接**單一 lihi Wordpress API**。Auth 已合併到 Short-URL API 的 `wordpress/v1` namespace；email 驗證、登入取得 bearer token、profile、短網址查詢 / 建立都走同一個 base URL。Auth routes 仍保留 `/auth` 子路徑。

外掛目前發版 metadata 為 `1.0.2`，WordPress.org slug / text domain / 發佈資料夾名稱為 `lihi-short-url`，WordPress.org readme 也標示本外掛維護於 `weedgood/lihi-wp-plugin`。本版加強 auth 身份驗證，透過 payload `hostname` + site-scoped `uuid` 辨識 WP 站台，避免依賴可能被 proxy / load balancer 改寫的 HTTP `Host` header；停用或刪除外掛時會清除本機保存的 lihi email、redirect domain、site UUID / UUID lock 與 JWT transient。以下 endpoint、request / response shape、error mapping 為現行契約。

Base URL:
- Production: `https://app.lihi.com/api/wordpress/v1`
- Dev: `https://app.lihidev.com/api/wordpress/v1`

Auth / non-auth contract:

| Endpoint | Auth | 契約 |
|---|---|---|
| `POST /auth/login` | none | ✅ 以已驗證 email 換取 bearer token |
| `POST /auth/update-email` | none | ✅ 建立 / 更新 email 驗證 token |
| `GET /auth/verify-email` | none | 瀏覽器 HTML flow；外掛不直接呼叫 |
| `GET /profile` | bearer token | ✅ |
| `GET /sites` / `POST /sites` | bearer token | ✅ |

JWT endpoints（`/profile`、`/sites`）需要 header:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Auth endpoints（`/auth/login`、`/auth/update-email`）不需要 Bearer token；它們以 JSON payload 的 `hostname` + `uuid` + `email` 識別 WP 站台與 email 驗證關係。

> 本 contract 不包含 `POST /mail`、`PUT/PATCH/DELETE /sites`、`/posts` 或 `/site-urls` 系列 endpoints。

---

## POST `/auth/update-email`

送出 email 驗證請求。成功回應代表 email 已驗證，或驗證信已送出。

伺服器行為：
- 若 `(domain, email, uuid)` 已 `verified = true` → 直接回 `{ verified: true }`，不寫 DB、不發 token。
- 若 row 不存在或未驗證 → 簽發新 verification token 並 upsert；驗證連結走 `GET /auth/verify-email?token=...` 的瀏覽器 HTML flow。

Body:
```json
{ "email": "alice@example.com", "hostname": "example.com", "uuid": "2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e" }
```

Response 200:
```json
{ "result": true, "data": { "verified": true } }
```
```json
{ "result": true, "data": { "verified": false } }
```

**錯誤：HTTP 400** → `Lihi_Validation_Exception`
- Laravel validation error：`{ "result": false, "msg": { ... } }`
- `hostname` 正規化後為空：`{ "result": false, "msg": "bad request" }`

**錯誤：HTTP 429** → `Lihi_Rate_Limit_Exception`
由 route middleware `throttle:10,1` 控制：每分鐘 10 次。

**錯誤：HTTP 500** → `Lihi_Server_Exception`
寄信、token 產生或 DB 寫入失敗時可能回 server error；真正錯誤只記在 server log，不應回給 client。

---

## POST `/auth/login`

以已驗證 email 換取 bearer token。登入請求會額外帶 `is_mobile`，值來自 WordPress `wp_is_mobile()`，表示當下 request 是否被 WordPress 判斷為 mobile browser。

Body:
```json
{ "email": "alice@example.com", "hostname": "example.com", "uuid": "2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e", "is_mobile": false }
```

Response 200:
```json
{ "result": true, "msg": "", "data": { "token": "eyJhbGci..." } }
```

- `data.token` — 後續呼叫 `/profile`、`/sites` 使用的 bearer token。

**錯誤：HTTP 400** → `Lihi_Validation_Exception`
- Laravel validation error：`{ "result": false, "msg": { ... } }`
- `hostname` 正規化後為空：`{ "result": false, "msg": "bad request" }`
- request IP 無效：`{ "result": false, "msg": "bad request" }`

**錯誤：HTTP 403** → `Lihi_Auth_Exception`
```json
{ "result": false, "msg": "email not verified" }
```
row 不存在、email 未驗證、或 `(domain, email, uuid)` 不符合時刻意回同一訊息，避免 caller 透過此端點試探 email / tenant 狀態。

**錯誤：HTTP 200，`result: false`**
```json
{ "result": false, "msg": "User Invalid" }
```
```json
{ "result": false, "msg": "Location Invalid" }
```
目前 controller 對已刪除 user 或 geoip 擋下的新 user 註冊會回 HTTP 200 + `result: false`；plugin client 應視為 auth / server failure，而不是成功 token。

---

## GET `/auth/verify-email`

使用者從驗證信點擊的 HTML flow；外掛不直接呼叫。

Query params:
- `token`

成功回 HTML view `wordpress.verify_success`；失敗回 404 HTML view `wordpress.verify_not_found`。

---

## GET `/profile`

讀取目前 JWT 對應帳號的 profile。

Auth: bearer token required.

Response 200:
```json
{
  "result": true,
  "data": {
    "user_role": "admin",
    "end_date": "2026-12-31",
    "domains": ["redirect.lihidev.com", "redirect2.lihidev.com"]
  }
}
```

- `user_role` / `end_date` 可能為 `null`（user 無 role 或 plan）
- `domains` 為可用 redirect domain 名稱陣列；包含 user available domains 與非 site-status 專用的 default domains

**錯誤：token 缺少或無效**
由 JWT middleware / upstream 錯誤處理決定；plugin 端目前以 token invalid / server exception 處理。

---

## GET `/sites`

查詢既有短網址 site records。

Auth: bearer token required.

Query params: `type`, `type_id`, `per_page`, `page`, `keyword`

- `type` + `type_id` 合起來是 WordPress link 查詢條件；不帶 `type` 時回傳所有 type 的結果
- `type_id` 需傳字串
- 本外掛在呼叫時會將 `type` 串上網站本身的 host（格式 `"{type}:{host}"`，例如 `post:example.com`），以便同一 lihi 帳號下多個 WordPress 站台共用相同 `type_id` 時仍可區分

Response:
```json
{
  "result": true,
  "data": {
    "domains": [ { "id": "redirect.lihidev.com", "name": "redirect.lihidev.com" } ],
    "total_sites": "0",
    "limit_sites": 300,
    "sites": {
      "current_page": 1,
      "total": 0,
      "per_page": 5,
      "data": [
        {
          "id": 123,
          "domain_name": "redirect.lihidev.com",
          "short_url": "https://redirect.lihidev.com/abc",
          "site_urls": [ { "id": 1, "url": "https://example.com" } ],
          "wordpress_link": { "type": "post:example.com", "type_id": "42" }
        }
      ]
    }
  }
}
```

**錯誤：token 缺少或無效**
由 JWT middleware / upstream 錯誤處理決定；plugin 端目前以 token invalid / server exception 處理。

---

## POST `/sites`

建立新的短網址 site record。

Auth: bearer token required.

伺服器端要求 `domain`、`urls`、`type` 必填；`type_id` 可選但傳字串。

Body:
```json
{
  "domain": "redirect.lihidev.com",
  "urls": ["https://example.com/?p=42"],
  "type": "post:example.com",
  "type_id": "42",
  "tags": "wordpress,example.com,post"
}
```

本外掛送出的 `type` 會帶上 WP 站台 host（格式 `"{type}:{host}"`），與 `GET /sites` 的查詢條件一致；`tags` 仍使用未串接的原始 `type`。因 API 要求 `domain` 必填，外掛會在 redirect domain 未設定時停用短網址建立流程並提早回傳友善錯誤；任何到達此端點的呼叫都已保證 `domain` 非空。

Response:
```json
{
  "result": true,
  "data": {
    "id": 456,
    "domain_name": "redirect.lihidev.com",
    "short_url": "https://redirect.lihidev.com/xyz",
    "site_urls": [],
    "wordpress_link": { "type": "post:example.com", "type_id": "42" }
  }
}
```

**錯誤：欄位缺失（HTTP 400）**
```json
{
  "result": false,
  "msg": {
    "domain": ["The domain field is required."],
    "urls":   ["The urls field is required."],
    "type":   ["The type field is required."]
  }
}
```

**錯誤：建立失敗（HTTP 400）**
```json
{ "result": false, "msg": "error" }
```

**錯誤：token 缺少或無效**
由 JWT middleware / upstream 錯誤處理決定；plugin 端目前以 token invalid / server exception 處理。
