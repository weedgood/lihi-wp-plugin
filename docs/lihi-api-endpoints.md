# lihi API Endpoints（WordPress Plugin）

Base URL:
- Production: `https://app.lihi.com/api/wordpress/v1`
- Dev: `https://app.lihidev.com/api/wordpress/v1`

Auth header（login 以外都需要）:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

---

## POST `/login`

Body:
```json
{ "email": "string", "api_key": "string", "country": "TW" }
```

Response:
```json
{ "result": true, "token": "string" }
```

Token TTL: 約 168 天。

**錯誤：欄位缺失（HTTP 400）**
```json
{
  "result": false,
  "msg": {
    "email":   ["The email field is required."],
    "api_key": ["The api key field is required."]
  }
}
```

**錯誤：api_key 錯誤（HTTP 200）**
```json
{ "result": false, "msg": "API Key error" }
```
注意：HTTP 仍為 200，`request()` 不拋例外；`login()` 靠檢查 `token` 是否為空間接處理。

**注意：email 不存在會自動建立新帳號**
api_key 正確時，不論 email 是否已存在都回傳 token；若 email 不存在則自動註冊新帳號。

**錯誤：伺服器錯誤（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## GET `/posts`

Query: `locale=zh-TW|en`

Response:
```json
{ "result": true, "data": [ { "id": 1, "title": "string", "body": "string" } ] }
```

**錯誤：token 缺少或無效（HTTP 500，HTML）**
不論是否帶 Authorization header 結果完全相同，回 HTML 頁面，`<title>` 固定為「網站升級中...」。

---

## GET `/sites`

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

Body（`domain` 必填，`type_id` 必須為字串）:
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

## PUT `/sites/{id}`

批次更新 site_urls 目標 URL（不能改 wordpress_link）:
```json
{ "urls": [ { "id": 789, "url": "https://example.com/new" } ] }
```

Response: `{ "result": true }`

**錯誤：ID 不存在（HTTP 404，HTML）**
`<title>` 為「Page Not Found」。

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## DELETE `/sites/{id}`

Response: `{ "result": true }`

**錯誤：ID 不存在（HTTP 500，HTML）**
與其他端點不同，回 HTTP 500 而非 404，`<title>` 為「網站升級中...」。

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## POST `/site-urls`

```json
{ "site_id": "456", "url": "https://example.com/extra" }
```

Response:
```json
{ "result": true, "data": { "id": 791, "site_id": 456, "url": "https://..." } }
```

**錯誤：欄位缺失（HTTP 400）**
```json
{
  "result": false,
  "msg": {
    "site_id": ["The site id field is required."],
    "url":     ["The url field is required."]
  }
}
```

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## PUT `/site-urls/{id}`

```json
{ "url": "https://example.com/updated" }
```

Response: `{ "result": true, "data": { "id": 791, "url": "https://..." } }`

**錯誤：ID 不存在（HTTP 404，HTML）**
`<title>` 為「Page Not Found」。

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## DELETE `/site-urls/{id}`

Response: `{ "result": true }`

**錯誤：ID 不存在（HTTP 404，HTML）**
`<title>` 為「Page Not Found」。

**錯誤：token 缺少或無效（HTTP 500，HTML）**
`<title>` 固定為「網站升級中...」。

---

## PHP Client 對照

| PHP 方法 | Method | Path |
|----------|--------|------|
| `login()` | POST | `/login` |
| `get_posts()` | GET | `/posts` |
| `get_sites()` | GET | `/sites` |
| `get_short_links()` | GET | `/sites` (per_page=20, type, type_id) |
| `create_site()` | POST | `/sites` |
| `update_site()` | PUT | `/sites/{id}` |
| `delete_site()` | DELETE | `/sites/{id}` |
| `create_site_url()` | POST | `/site-urls` |
| `update_site_url()` | PUT | `/site-urls/{id}` |
| `delete_site_url()` | DELETE | `/site-urls/{id}` |
