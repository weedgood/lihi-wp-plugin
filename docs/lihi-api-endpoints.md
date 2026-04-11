# Lihi API Endpoints（WordPress Plugin）

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

---

## GET `/posts`

Query: `locale=zh-TW|en`

Response:
```json
{ "result": true, "data": [ { "id": 1, "title": "string", "body": "string" } ] }
```

---

## GET `/sites`

Query params: `type`, `type_id`, `per_page`, `page`, `keyword`

- `type` + `type_id` 合起來是唯一 key；不帶 `type` 時回傳所有 type 的結果
- `type_id` 需傳字串

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

---

## POST `/sites`

Body（`domain` 必填，`type_id` 必須為字串）:
```json
{
  "domain": "redirect.lihidev.com",
  "urls": ["https://example.com/?p=42"],
  "type": "post",
  "type_id": "42",
  "tags": "wordpress,example.com,post"
}
```
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

---

## PUT `/sites/{id}`

批次更新 site_urls 目標 URL（不能改 wordpress_link）:
```json
{ "urls": [ { "id": 789, "url": "https://example.com/new" } ] }
```
Response: `{ "result": true }`

---

## DELETE `/sites/{id}`

Response: `{ "result": true }`

---

## POST `/site-urls`

```json
{ "site_id": "456", "url": "https://example.com/extra" }
```
Response:
```json
{ "result": true, "data": { "id": 791, "site_id": 456, "url": "https://..." } }
```

---

## PUT `/site-urls/{id}`

```json
{ "url": "https://example.com/updated" }
```
Response: `{ "result": true, "data": { "id": 791, "url": "https://..." } }`

---

## DELETE `/site-urls/{id}`

Response: `{ "result": true }`

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
