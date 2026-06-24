# lihi Wordpress API Endpoints

本外掛目前對接**單一 lihi Wordpress API**。Auth 已合併到 Short-URL API 的 `wordpress/v1` namespace；email 驗證、登入取得 bearer token、profile、建立選項、passthrough nonce、短網址查詢 / 建立都走同一個 base URL。Auth routes 仍保留 `/auth` 子路徑。

外掛目前發版 metadata 為 `1.0.3`，WordPress.org slug / text domain / 發佈資料夾名稱為 `lihi-short-url`，WordPress.org readme 也標示本外掛維護於 `weedgood/lihi-wp-plugin`。本版加強 auth 身份驗證，透過 payload `hostname` + site-scoped `uuid` 辨識 WP 站台，避免依賴可能被 proxy / load balancer 改寫的 HTTP `Host` header；停用或刪除外掛時會清除本機保存的 lihi email、legacy redirect domain、site UUID / UUID lock 與 JWT transient。以下 endpoint、request / response shape、error mapping 為現行契約。

Base URL:
- Production: `https://app.lihi.com/api/wordpress/v1`
- Dev: `https://app.lihidev.com/api/wordpress/v1`

Auth / non-auth contract:

| Endpoint | Auth | 契約 |
|---|---|---|
| `POST /auth/login` | none | ✅ 以已驗證 email 換取 bearer token |
| `POST /auth/update-email` | none | ✅ 以密碼完成既有帳號綁定，或建立 / 更新 email 驗證 token |
| `GET /auth/verify-email` | none | 瀏覽器 HTML flow；外掛不直接呼叫 |
| `GET /user/profile` | bearer token | ✅ |
| `GET /user/options` | bearer token | ✅ 建立 modal 的 domains / UTM options |
| `POST /passthrough/nonce` | bearer token | ✅ 產生短效 nonce，供瀏覽器 POST 到 passthrough redirect |
| `POST /passthrough/redirect` | nonce form post | 瀏覽器 HTML/session flow；不是 `Lihi_Client` method |
| `GET /site/find` / `POST /site/store` | bearer token | ✅ |

JWT endpoints（`/user/profile`、`/user/options`、`/passthrough/nonce`、`/site/find`、`/site/store`）需要 header:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Auth endpoints（`/auth/login`、`/auth/update-email`）不需要 Bearer token；它們以 JSON payload 的 `hostname` + `uuid` + `email` 識別 WP 站台與 email 驗證關係。

> 本 contract 不包含 `POST /mail`、site update/delete、`/posts` 或 `/site-urls` 系列 endpoints。

---

## POST `/auth/update-email`

送出 email / password 驗證請求。成功回應代表既有帳號密碼已驗證，或新帳號驗證信已送出。

伺服器行為：
- 每次請求會先清除同一個 `domain` 的其他 WordPress email 驗證紀錄。
- 若 email 對應既有 lihi 使用者且密碼正確、帳號可用 → 標記 `(domain, email, uuid)` 已驗證並回 `{ verified: true }`，不寄信。
- 若 email 對應既有 lihi 使用者但密碼錯誤 → 回 `password invalid`。
- 若 email 不存在 → 簽發新 verification token、保存密碼雜湊並寄出驗證信；驗證連結走 `GET /auth/verify-email?token=...` 的瀏覽器 HTML flow，回 `{ verified: false }`。

Body:
```json
{ "email": "alice@example.com", "hostname": "example.com", "uuid": "2df6f4f1-2a75-4d0e-9ce0-7c70e8d7bb9e", "password": "account-password" }
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

**錯誤：HTTP 403 `password invalid`** → `Lihi_Email_Or_Password_Invalid_Exception`
既有 lihi 帳號密碼錯誤；外掛會統一顯示友善的帳密錯誤訊息，且不保存 `lihi_email`。

**錯誤：HTTP 403 / 200 `User Invalid`** → `Lihi_User_Invalid_Exception`
帳號被刪除、停用或不可使用時，外掛顯示帳號不可使用。

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

- `data.token` — 後續呼叫 `/user/profile`、`/user/options`、`/site/find`、`/site/store` 使用的 bearer token。

**錯誤：HTTP 400** → `Lihi_Validation_Exception`
- Laravel validation error：`{ "result": false, "msg": { ... } }`
- `hostname` 正規化後為空：`{ "result": false, "msg": "bad request" }`
- request IP 無效：`{ "result": false, "msg": "bad request" }`

**錯誤：HTTP 403 `email not verified`** → `Lihi_Auth_Exception`
```json
{ "result": false, "msg": "email not verified" }
```
row 不存在、email 未驗證、或 `(domain, email, uuid)` 不符合時刻意回同一訊息，避免 caller 透過此端點試探 email / tenant 狀態。

**錯誤：HTTP 403 `User Invalid`** → `Lihi_User_Invalid_Exception`
```json
{ "result": false, "msg": "User Invalid" }
```
lihi 帳號不可使用（例如主帳號停用子帳號、或帳號被封鎖）時回傳；外掛端會顯示 account unavailable 訊息，而不是 email 未驗證訊息。

**錯誤：HTTP 200，`result: false`**
```json
{ "result": false, "msg": "Location Invalid" }
```
目前 controller 對 geoip 擋下的新 user 註冊可能回 HTTP 200 + `result: false`；plugin client 應視為 server failure，而不是成功 token。

---

## GET `/auth/verify-email`

使用者從驗證信點擊的 HTML flow；外掛不直接呼叫。

Query params:
- `token`

成功回 HTML view `wordpress.verify_success`；失敗回 404 HTML view `wordpress.verify_not_found`。

---

## GET `/user/profile`

讀取目前 JWT 對應帳號的 profile。

Auth: bearer token required.

Response 200:
```json
{
  "result": true,
  "data": {
    "user_role": "admin",
    "end_date": "2026-12-31"
  }
}
```

- `user_role` / `end_date` 可能為 `null`（user 無 role 或 plan）

---

## GET `/user/options`

讀取建立短網址 modal 需要的選項。

Auth: bearer token required.

Response 200:
```json
{
  "result": true,
  "msg": "",
  "data": {
    "domains": [
      { "id": 123, "name": "wp-domain.example" },
      { "id": "redirect.lihidev.com", "name": "redirect.lihidev.com" }
    ],
    "utm_sources": ["facebook", "newsletter"],
    "utm_mediums": ["social", "email"]
  }
}
```

- `domains` 為建立 modal 的 redirect-domain options；外掛會正規化成 `{ value, label }`，送出時使用 `value`
- `utm_sources` / `utm_mediums` 是建立 modal 中 `utm_source` / `utm_medium` 下拉選單的 options；其他 UTM 欄位仍由使用者輸入
- 前端透過 `lihi_url_options` AJAX 一次取得 domains 與 UTM options，並快取 60 秒

以下為 JWT endpoints（`/user/profile`、`/user/options`、`/site/find`、`/site/store`）共用錯誤映射。

**錯誤：帳號不可使用** → `Lihi_User_Invalid_Exception`
```json
{ "result": "failed", "msg": "user_not_found ,please login again" }
```
```json
{ "result": "failed", "msg": "User Invalid" }
```
JWT middleware 判斷 bearer token 對應 user 不存在或不可使用時回傳；外掛端會清掉本機 JWT transient，直接顯示 account unavailable 訊息，不在同一個 request 自動 login 重試。

**錯誤：token 缺少或無效** → `Lihi_Token_Invalid_Exception`
```json
{ "result": "failed", "msg": "Token invalid ,please login again" }
```
```json
{ "result": "failed", "msg": "Token expired ,please login again" }
```
```json
{ "result": "failed", "msg": "Something wrong ,please login again" }
```
JWT middleware 判斷 token invalid / expired / unexpected auth error 時回傳；外掛端會清掉本機 JWT transient 並自動 login 重試一次。若重試後仍失敗，設定頁 / Short URL AJAX 顯示 login session expired 訊息。

**其他 JSON failure / HTTP 5xx** → `Lihi_Server_Exception`
- 若 JWT endpoint 回傳 JSON envelope 但 `result !== true`，且不屬於 user invalid / token invalid / validation error，外掛端會視為 lihi 服務錯誤，不會把 options 當空陣列或把短網址查詢當作不存在。

---

## POST `/passthrough/nonce`

建立 30 秒內有效的 passthrough nonce，供瀏覽器 POST 到 `/passthrough/redirect`，讓 lihi-admin 建立 web session 後導向 SaaS 站台列表。這是 server-to-server JSON endpoint，已加入 `Lihi_Client` contract；真正建立 session 的 redirect endpoint 需由瀏覽器送出 form post，不屬於 HTTP client method。

Auth: bearer token required.

Body:
```json
{
  "challenge": "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
  "target": "https://example.com/demo-path?foo=bar"
}
```

- `challenge` 為瀏覽器產生 verifier 後計算的 `base64url(sha256(verifier))`，長度 43，供 redirect form POST 時用 verifier 驗證。
- `target` 可省略或為空字串；外掛 Edit button 會先查目前短網址，並把該短網址作為 `target`，有值時 SaaS redirect 會以 base64 keyword 帶到 `/admin/site` 搜尋。
- `target` 最大長度 2048；`challenge` 必填。

Response 200:
```json
{
  "result": true,
  "msg": "",
  "data": {
    "nonce": "eyJhbGci..."
  }
}
```

**錯誤：欄位驗證失敗（HTTP 400）** → `Lihi_Validation_Exception`
```json
{ "result": false, "msg": { "target": ["The target may not be greater than 2048 characters."] } }
```

**錯誤：rate limited（HTTP 429）** → `Lihi_Rate_Limit_Exception`
由 route middleware `throttle:10,1` 控制：每分鐘 10 次。

**錯誤：帳號不可使用** → `Lihi_User_Invalid_Exception`
```json
{ "result": "failed", "msg": "User Invalid" }
```
JWT middleware 判斷 bearer token 對應 user 不可使用時回傳。

**錯誤：token 缺少或無效** → `Lihi_Token_Invalid_Exception`
```json
{ "result": "failed", "msg": "Token invalid ,please login again" }
```
```json
{ "result": "failed", "msg": "Token expired ,please login again" }
```
```json
{ "result": "failed", "msg": "Something wrong ,please login again" }
```

---

## POST `/passthrough/redirect`

瀏覽器 HTML/session flow。外掛 Edit button 先向 `POST /passthrough/nonce` 取得 nonce，再用瀏覽器 hidden form POST 到此 endpoint；成功時 lihi-admin 會建立 web session cookie 並 redirect。

Body:
```json
{
  "nonce": "eyJhbGci...",
  "verifier": "BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB"
}
```

- `verifier` 為瀏覽器在 Edit flow 開始時產生的 base64url random string；lihi-admin 會驗證 `base64url(sha256(verifier))` 是否等於 nonce payload 中的 `challenge`。

成功：
- `target` 空 → redirect `/admin/site`
- `target` 有值 → redirect `/admin/site?keyword={base64(target)}`

此 endpoint 不是 `Lihi_Client` method，因為它的副作用是瀏覽器 web session。

---

## GET `/site/find`

查詢單一既有短網址。

Auth: bearer token required.

Query params: `type`, `type_id`

- `type` + `type_id` 合起來是 WordPress link 查詢條件，兩者皆必填
- `type_id` 需傳字串
- 本外掛在呼叫時會將 `type` 串上網站本身的 host（格式 `"{type}:{host}"`，例如 `post:example.com`），以便同一 lihi 帳號下多個 WordPress 站台共用相同 `type_id` 時仍可區分

Response 200，有既有短網址:
```json
{
  "result": true,
  "msg": "",
  "data": {
    "site": "https://redirect.lihidev.com/abc"
  }
}
```

Response 200，無既有短網址:
```json
{
  "result": true,
  "msg": "",
  "data": {
    "site": ""
  }
}
```

- `site` 是短網址字串；空字串表示沒有既有短網址
- 此 endpoint 不回傳 domains 或 UTM options；建立 modal 選項請使用 `GET /user/options`

**錯誤：欄位驗證失敗（HTTP 400）** → `Lihi_Validation_Exception`
```json
{ "result": false, "msg": { "type": ["The type field is required."], "type_id": ["The type id field is required."] } }
```

**錯誤：帳號不可使用** → `Lihi_User_Invalid_Exception`
```json
{ "result": "failed", "msg": "user_not_found ,please login again" }
```
```json
{ "result": "failed", "msg": "User Invalid" }
```
JWT middleware 判斷 bearer token 對應 user 不存在或不可使用時回傳；外掛端會清掉本機 JWT transient，直接顯示 account unavailable 訊息，不在同一個 request 自動 login 重試。

**錯誤：token 缺少或無效** → `Lihi_Token_Invalid_Exception`
```json
{ "result": "failed", "msg": "Token invalid ,please login again" }
```
```json
{ "result": "failed", "msg": "Token expired ,please login again" }
```
```json
{ "result": "failed", "msg": "Something wrong ,please login again" }
```
JWT middleware 判斷 token invalid / expired / unexpected auth error 時回傳；外掛端會清掉本機 JWT transient 並自動 login 重試一次。若重試後仍失敗，設定頁 / Short URL AJAX 顯示 login session expired 訊息。

---

## POST `/site/store`

建立新的短網址 site record。

Auth: bearer token required.

伺服器端要求 `domain`、`urls`、`type` 必填；`type_id` 可選但傳字串。

Body:
```json
{
  "domain": "redirect.lihidev.com",
  "urls": ["https://example.com/?p=42&utm_source=newsletter&utm_medium=email&utm_campaign=spring-sale"],
  "type": "post:example.com",
  "type_id": "42",
  "tags": "wordpress,example.com,post,campaign"
}
```

本外掛送出的 `type` 會帶上 WP 站台 host（格式 `"{type}:{host}"`），與 `GET /site/find` 的查詢條件一致；預設 tag 內容為 `wordpress`、WP 站台 host、未串接的原始 `type`，會先與建立 modal 中使用者輸入的 tags 合併去重，再以逗號分隔字串送到 lihi API。建立 modal 會從 `lihi_url_options` 透過 `GET /user/options` 載入 domains 與 `utm_sources` / `utm_mediums`，前端會將整包 options 快取 60 秒；Domain、UTM source、UTM medium 使用同一套 select loading / option rendering UI，沒有 account-default fallback option。送出建立請求時，外掛只要求 `domain` 為非空值，不會為了檢查 membership 再打一次 options API，最終 domain 是否有效交由 lihi API 判斷。UTM 欄位只會附加到目的 URL query string，不會以獨立 `utm` object 送到 lihi API；lihi-admin 端會由 `site_urls.url` 的 `utm_*` query params 解析 UTM。缺少本機 `lihi_domain` option 不會阻擋 lihi button 顯示或短網址建立流程。PHP 只輸出空的 `data-lihi-container` 掛載容器，前端會把實際 button 放入 container；建立流程使用 WP AJAX action `lihi_create_url`，Copy 檢查流程使用 `lihi_copy_url`。當 `GET /site/find` 找到既有短網址或 `POST /site/store` 成功建立短網址後，外掛會在該 WP post / attachment 寫入 post meta `lihi_already = 1`，之後前端依 `data-lihi-already` 將按鈕文案渲染為 `Copy`；只有目前 WP 使用者具備 `manage_options` 時，才會在旁邊顯示 `Edit`。若瀏覽器阻擋 clipboard 寫入，前端會直接彈出短網址讓使用者手動複製。`Copy` 狀態點擊時仍會呼叫 `GET /site/find` 確認短網址存在；若不存在，外掛會寫入 `lihi_already = 0`，回傳 HTTP 410 / `code = lihi_missing`，前端在確認 modal 顯示「Short URL has been removed. Please create it again.」，使用者按 OK 後才開啟建立 modal；其他 Copy API 錯誤只顯示錯誤訊息，不會重設按鈕狀態。管理者點擊 `Edit` 時會先顯示「Go to the lihi dashboard to edit this short URL?」，確認後前端會先開空白分頁、產生 verifier 與 `base64url(sha256(verifier))` challenge，再使用 WP AJAX action `lihi_edit_url` 查一次既有短網址；該 AJAX 也會檢查 `manage_options`，若存在，外掛以短網址作為 passthrough `target` 並帶 challenge 取得 nonce，前端再用 hidden form POST `nonce` + `verifier` 到 `/passthrough/redirect`。若短網址已不存在，Edit 也會回傳同一組 HTTP 410 / `lihi_missing`，前端改回 `lihi` 並顯示短網址已遭移除訊息。

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

**錯誤：帳號不可使用** → `Lihi_User_Invalid_Exception`
```json
{ "result": "failed", "msg": "user_not_found ,please login again" }
```
```json
{ "result": "failed", "msg": "User Invalid" }
```
JWT middleware 判斷 bearer token 對應 user 不存在或不可使用時回傳；外掛端會清掉本機 JWT transient，直接顯示 account unavailable 訊息，不在同一個 request 自動 login 重試。

**錯誤：token 缺少或無效** → `Lihi_Token_Invalid_Exception`
```json
{ "result": "failed", "msg": "Token invalid ,please login again" }
```
```json
{ "result": "failed", "msg": "Token expired ,please login again" }
```
```json
{ "result": "failed", "msg": "Something wrong ,please login again" }
```
JWT middleware 判斷 token invalid / expired / unexpected auth error 時回傳；外掛端會清掉本機 JWT transient 並自動 login 重試一次。若重試後仍失敗，設定頁 / Short URL AJAX 顯示 login session expired 訊息。
