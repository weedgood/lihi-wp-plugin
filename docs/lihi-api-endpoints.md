# lihi API Endpoints

本外掛對接兩個獨立服務：
- **lihi short-URL API** — 建立、查詢短網址與讀取帳號 profile
- **lihi auth 服務** — email 驗證與登入取得 bearer token

外掛目前發版 metadata 為 `1.0.2`，WordPress.org slug / text domain / 發佈資料夾名稱為 `lihi-short-url`，WordPress.org readme 也標示本外掛維護於 `weedgood/lihi-wp-plugin`。本版加強 auth 身份驗證，透過 payload `hostname` + site-scoped `uuid` 辨識 WP 站台，避免依賴可能被 proxy / load balancer 改寫的 HTTP `Host` header；以下 endpoint、request / response shape、error mapping 為現行契約。

---

# lihi Short-URL API

Base URL:
- Production: `https://app.lihi.com/api/wordpress/v1`
- Dev: `https://app.lihidev.com/api/wordpress/v1`

Auth header（所有端點都需要，token 由 Auth API `POST /auth/login` 取得）:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

> Short-URL API 的 `wordpress/v1` contract 只涵蓋**非 auth 的 JWT endpoints**；
> auth 流程全部走 lihi Auth API：
>
> | Endpoint | Auth | 契約 |
> |---|---|---|
> | `POST /auth/login` | legacy api_key | ❌（token 由 lihi Auth API 取得） |
> | `POST /auth/mail` | legacy api_key | ❌（外掛無用途） |
> | `GET /profile` | jwt | ✅ |
> | `GET /sites` / `POST /sites` | jwt | ✅ |
>
> 註：short-URL API contract 不包含 `POST /login`、`POST /mail`、`PUT/PATCH/DELETE /sites`、
> `/posts` 或 `/site-urls` 系列 endpoints。

---

## GET `/profile`

讀取目前 JWT 對應帳號的 profile。

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
- `domains` 為可用 redirect domain 名稱陣列

**錯誤：token 缺少或無效（HTTP 500，HTML）** → `Lihi_Token_Invalid_Exception`。

---

## GET `/sites`

查詢既有短網址 site records。

Query params: `type`, `type_id`, `per_page`, `page`, `keyword`

- `type` + `type_id` 合起來是唯一 key；不帶 `type` 時回傳所有 type 的結果
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
          "wordpress_link": { "type": "post", "type_id": "42" }
        }
      ]
    }
  }
}
```

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## POST `/sites`

建立新的短網址 site record。伺服器端要求 `domain`、`urls`、`type` 必填；`type_id` 可選但傳字串。

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
    "wordpress_link": { "type": "post", "type_id": "42" }
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

**注意：無效 domain 不報錯**
傳入不屬於帳號的 domain 時 API 不報錯，自動換成帳號下的有效 domain 建立。

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

# lihi Auth API

Base URL: `lihi_config( 'auth_domain' )`（例：`https://w.lihidev.com`）。**不同服務**，不要與 `api_domain`（short-URL API）混淆。

每次呼叫都必須在 JSON body 帶 `hostname` 與 `uuid` 欄位。`hostname` 值為 WP 站台本身的 hostname（由 `home_url()` 解析而來，不含 port）；`uuid` 是第一次 auth 取用時產生並保存到 `lihi_uuid` option 的站台識別碼（若 option 不存在，第一次 auth request 會補建）。auth 服務靠這些 payload 欄位判斷 tenant，而非 HTTP `Host` header，避免 proxy / load balancer 將 `Host` 改寫成 auth 服務自己的 domain。

Response 一律使用 envelope `{ result: bool, data: {...} }`；失敗時 `data.message` 為錯誤字串。

## POST `/auth/update-email`

送出 email 驗證請求。成功回應代表 email 已驗證，或驗證信已送出。

伺服器行為：
- 若 `(domain, email)` 已 `verified = true` → 直接回 `{ verified: true }`，不寫 DB、不發 token。
- 若 row 不存在或未驗證 → 一律簽發新 JWT 並 upsert（會使先前的 token 失效）。該 JWT 由 auth 服務以 email 等方式 out-of-band 寄出，**不會**在回應中回傳。

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
`data.message` 可能為：`invalid json body`、`email is required`、`invalid email`（RFC 5322 parse 失敗）、`bad request`（payload hostname / uuid 缺失；刻意回通用訊息以作防偽閘道）。

**錯誤：HTTP 429** → `Lihi_Rate_Limit_Exception`
每 hostname 10 req/min（payload `hostname` 經 lowercase / 去 `:port` 正規化後計數）；空 hostname 共用同一 fallback bucket。fixed-window、per-process。`data.message`：`too many requests`。

**錯誤：HTTP 500** → `Lihi_Server_Exception`
`data.message` ∈ `load verification`、`issue token`、`persist verification`。真正的錯誤只記在 server log，不回給 client。

---

## POST `/auth/login`

登入請求會額外帶 `is_mobile`，值來自 WordPress `wp_is_mobile()`，表示當下 request 是否被 WordPress 判斷為 mobile browser。

Body:
```json
{ "email": "alice@example.com", "hostname": "example.com", "uuid": "2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e", "is_mobile": false }
```

Response 200:
```json
{ "result": true, "data": { "token": "eyJhbGci..." } }
```

- `data.token` — lihi 上游 bearer token（上游 TTL 約 168 天）。

**錯誤：HTTP 400** → `Lihi_Validation_Exception`（觸發條件同 `/auth/update-email`）
**錯誤：HTTP 403** → `Lihi_Auth_Exception`。`data.message`：`email not verified`。row 不存在與 row 未驗證刻意回傳同一訊息，避免 caller 透過這個端點試探 email 是否存在。
**錯誤：HTTP 500** → `Lihi_Server_Exception`。`data.message` ∈ `load verification`、`issue token`；`issue token` 把所有上游失敗模式（validation、bad api_key、upstream 維護頁、network error）收斂到同一個訊息，避免 caller fingerprint 上游狀態。

> 不實作 `GET /healthz`、`GET /readyz`（監控用）、`GET /auth/verify-email`（使用者在
> 瀏覽器點信件中連結的 HTML flow）— 外掛不會呼叫這些 endpoint。
