# lihi API Endpoints（WordPress Plugin）

本外掛對接兩個獨立服務：
- **lihi short-URL API**（見 `lihi-admin`）— 下方的 `Lihi_Client` 契約
- **lihi auth 服務**（見 `/home/wayne/lihi-wp-auth/docs/api.md`）— 下方的 `Lihi_Auth_Client` 契約

---

# lihi Short-URL API（Lihi_Client）

Base URL:
- Production: `https://app.lihi.com/api/wordpress/v1`
- Dev: `https://app.lihidev.com/api/wordpress/v1`

Auth header（所有端點都需要，token 由 `Lihi_Auth_Client::login()` 取得）:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

> `wordpress/v1` routes（見 `lihi-admin/routes/api.php`）掛載下列 endpoint，`Lihi_Client`
> 契約只涵蓋**非 auth 的 jwt endpoints**（auth 全部走下方 Lihi_Auth_Client）：
>
> | Endpoint | Auth | 契約 |
> |---|---|---|
> | `POST /auth/login` | legacy api_key | ❌（由 `Lihi_Auth_Client` 對 lihi-wp-auth 取 token） |
> | `POST /auth/mail` | legacy api_key | ❌（外掛無用途） |
> | `GET /profile` | jwt | ✅ |
> | `GET /sites` / `POST /sites` | jwt | ✅ |
>
> 註：lihi-admin 已把 `login` / `mail` 移到 `auth` prefix 下，原本的 `POST /login` /
> `POST /mail` 不再存在。`SiteController` 雖有 `update` / `destroy` 方法，但 route
> 未掛載；`/posts`、`/site-urls` 則完全不存在於此 route group。

---

## GET `/profile`

對應 `AuthController@profile`，jwt 驗證，外掛不使用。

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

對應 `SiteController@index`。

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

對應 `SiteController@store`。伺服器端 Validator 要求 `domain`、`urls`、`type` 必填；`type_id` 可選但傳字串。

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

本外掛送出的 `type` 會帶上 WP 站台 host（格式 `"{type}:{host}"`），與 `GET /sites` 的查詢條件一致；`tags` 仍使用未串接的原始 `type`。

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

---

## PHP Client 對照

| PHP 方法 | Method | Path | Auth |
|----------|--------|------|------|
| `get_profile()` | GET | `/profile` | jwt (外掛不呼叫) |
| `get_sites()` | GET | `/sites` | jwt |
| `get_short_links()` | GET | `/sites` (per_page=20, type, type_id) | jwt |
| `create_site()` | POST | `/sites` | jwt |

---

# lihi Auth API（Lihi_Auth_Client）

Base URL: `lihi_config( 'auth_domain' )`（例：`https://w.lihidev.com`）。**不同服務**，不要與 `api_domain`（short-URL API）混淆。

完整規格見 sibling repo：`/home/wayne/lihi-wp-auth/docs/api.md`。

`Lihi_Auth_Client` 每次呼叫都把 HTTP `Host` header 覆寫成 WP 站台本身的 host（取自 `home_url()`）；auth 服務靠這個 header 判斷 tenant（`Host` 會 lowercase 並去掉 `:port`），而非 URL 中的 host。

Response 一律使用 envelope `{ result: bool, data: {...} }`；失敗時 `data.message` 為錯誤字串。

## POST `/auth/update-email`

由外掛設定頁的「Save & Verify」按鈕觸發：`wp_ajax_lihi_update_email` handler 先呼叫本端點，成功才 `update_option('lihi_email', $email)`，避免被 auth 服務拒絕的 email 成為有效設定。

伺服器行為：
- 若 `(domain, email)` 已 `verified = true` → 直接回 `{ verified: true }`，不寫 DB、不發 token。
- 若 row 不存在或未驗證 → 一律簽發新 JWT 並 upsert（會使先前的 token 失效）。該 JWT 由 auth 服務以 email 等方式 out-of-band 寄出，**不會**在回應中回傳。

Body:
```json
{ "email": "alice@example.com" }
```

Response 200:
```json
{ "result": true, "data": { "verified": true } }
```
```json
{ "result": true, "data": { "verified": false } }
```

**錯誤：HTTP 400** → `Lihi_Validation_Exception`
`data.message` 可能為：`invalid json body`、`email is required`、`invalid email`（RFC 5322 parse 失敗）、`bad request`（Host header 缺失；刻意回通用訊息以作防偽閘道）。

**錯誤：HTTP 429** → `Lihi_Rate_Limit_Exception`
每 host 10 req/min（`Host` 經 lowercase / 去 `:port` 正規化後計數）；空 Host 共用同一 fallback bucket。fixed-window、per-process。`data.message`：`too many requests`。

**錯誤：HTTP 500** → `Lihi_Server_Exception`
`data.message` ∈ `load verification`、`issue token`、`persist verification`。真正的錯誤只記在 server log，不回給 client。

---

## POST `/auth/login`

Body:
```json
{ "email": "alice@example.com" }
```

Response 200:
```json
{ "result": true, "data": { "token": "eyJhbGci..." } }
```

- `data.token` — lihi 上游 bearer token（上游 TTL 約 168 天）。

**錯誤：HTTP 400** → `Lihi_Validation_Exception`（觸發條件同 `/auth/update-email`）
**錯誤：HTTP 403** → `Lihi_Auth_Exception`。`data.message`：`email not verified`。row 不存在與 row 未驗證刻意回傳同一訊息，避免 caller 透過這個端點試探 email 是否存在。
**錯誤：HTTP 500** → `Lihi_Server_Exception`。`data.message` ∈ `load verification`、`issue token`；`issue token` 把所有上游失敗模式（validation、bad api_key、upstream 維護頁、network error）收斂到同一個訊息，避免 caller fingerprint 上游狀態。

---

## PHP Auth Client 對照

| PHP 方法 | Method | Path |
|----------|--------|------|
| `Lihi_Auth_Client::update_email()` | POST | `/auth/update-email` |
| `Lihi_Auth_Client::login()` | POST | `/auth/login` |

> 不實作 `GET /healthz`、`GET /readyz`（監控用）、`GET /auth/verify-email`（使用者在
> 瀏覽器點信件中連結的 HTML flow）— 外掛不會呼叫這些 endpoint。
