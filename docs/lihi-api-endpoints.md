# Lihi API Endpoints

## 原始碼位置

| 檔案 | 說明 |
|------|------|
| `lihi-shopify-app/web/middleware/lihi-api.js` | 主要 API middleware |
| `lihi-shopify-app/web/middleware/shopify-api.js` | 含 delete site |
| `lihi-shopify-app/web/helpers/short-link.js` | getLihiShortLinks / getPairedData |

---

## Endpoints

### POST `/api/shopify/v1/login`
**檔案**: `lihi-api.js:50`

Body:
```json
{ "email": "string", "api_key": "string" }
```
Response:
```json
{ "token": "string" }
```
前端使用: `index.jsx:13` — 取 `res.token` 存入 localStorage

---

### GET `/api/shopify/v1/posts`
**檔案**: `lihi-api.js:66`

Headers: `Authorization: Bearer <token>`

Response:
```json
{ "data": [ { "id": "number", "title": "string", "...": "..." } ] }
```
前端使用: `Announcement.jsx:15` — 取 `res.data[last]`

---

### GET `/api/shopify/v1/sites`
**檔案**: `lihi-api.js:80`, `lihi-api.js:126`, `lihi-api.js:155`, `helpers/short-link.js:4`

Headers: `Authorization: Bearer <token>`

Query params:

| 參數 | 型別 | 說明 |
|------|------|------|
| `type` | string | `products` / `collections` / `pages` / `customizations` |
| `type_id` | string | 逗號分隔的 ID |
| `per_page` | number | 預設 20 |
| `keyword` | string | 關鍵字搜尋 |

Response:
```json
{
  "data": {
    "domains": ["string"],
    "sites": {
      "data": [
        {
          "id": "number",
          "site_name": "string",
          "repeat_click": "number",
          "site_urls": [ { "id": "number", "url": "string", "count": "number" } ],
          "shopify_link": { "type": "string", "type_id": "number" }
        }
      ],
      "prev_page_url": "string | null",
      "next_page_url": "string | null"
    }
  }
}
```
前端使用: `ShortLinks.jsx:150-152` — 取 `res.sites.data`, `res.sites.prev_page_url`, `res.sites.next_page_url`

---

### POST `/api/shopify/v1/sites`
**檔案**: `lihi-api.js:94`，前端呼叫: `ShortLinkModal.jsx:43`

Headers: `Content-Type: application/json`, `Authorization: Bearer <token>`

Body:
```json
{
  "tags": "string",
  "urls": ["string"],
  "alias": "string",
  "domain": "string",
  "type": "string (products | collections | pages | customizations)",
  "type_id": "number (有 target 時才帶)"
}
```
Response:
```json
{
  "data": {
    "id": "number",
    "site_name": "string",
    "shopify_link": { "type": "string", "type_id": "number" }
  }
}
```
前端使用: `ShortLinkModal.jsx:61-70` — 取 `lihiRes.data.shopify_link`, `lihiRes.data.id`, `lihiRes.data.site_name`

---

### DELETE `/api/shopify/v1/sites/{id}`
**檔案**: `shopify-api.js:231`，前端呼叫: `ShortLinks.jsx:165`（無 body）

Headers: `Authorization: Bearer <token>`

Response: 無（前端只更新本地 state，不使用回應內容）

---

### POST `/api/shopify/v1/site-urls`
**檔案**: `lihi-api.js:211`，前端呼叫: `AddSiteUrl.jsx:24`

Headers: `Content-Type: application/json`, `Authorization: Bearer <token>`

Body:
```json
{
  "site_id": "string (number.toFixed())",
  "url": "string"
}
```
Response:
```json
{ "data": { "id": "number", "url": "string", "...": "..." } }
```
前端使用: `AddSiteUrl.jsx:33` — 取 `res.data`

---

### PUT `/api/shopify/v1/site-urls/{id}`
**檔案**: `lihi-api.js:190`，前端呼叫: `EditSiteUrl.jsx:21`

Headers: `Content-Type: application/json`, `Authorization: Bearer <token>`

Body:
```json
{ "url": "string" }
```
Response: 不使用（前端直接用本地 `url` 更新 state，`EditSiteUrl.jsx:27`）

---

### DELETE `/api/shopify/v1/site-urls/{id}`
**檔案**: `lihi-api.js:173`，前端呼叫: `ShortLinks.jsx:182`

Headers: `Authorization: Bearer <token>`

Response:
```json
{ "result": true }
```
前端使用: `ShortLinks.jsx:187` — 只確認成功，不取值

---

## PHP Client 對照

| PHP 方法 | Method | Path | 檔案 | 狀態 |
|----------|--------|------|------|------|
| `login()` | POST | `/api/shopify/v1/login` | `lihi-api.js:50` | ✅ |
| `get_posts()` | GET | `/api/shopify/v1/posts` | `lihi-api.js:66` | ✅ |
| `get_sites()` | GET | `/api/shopify/v1/sites` | `lihi-api.js:80` | ✅ |
| `get_short_links()` | GET | `/api/shopify/v1/sites` (per_page=20) | `helpers/short-link.js:3` | ✅ |
| `create_site()` | POST | `/api/shopify/v1/sites` | `lihi-api.js:94` | ✅ |
| `delete_site()` | DELETE | `/api/shopify/v1/sites/{id}` | `shopify-api.js:231` | ✅ |
| `create_site_url()` | POST | `/api/shopify/v1/site-urls` | `lihi-api.js:211` | ✅ |
| `update_site_url()` | PUT | `/api/shopify/v1/site-urls/{id}` | `lihi-api.js:190` | ✅ |
| `delete_site_url()` | DELETE | `/api/shopify/v1/site-urls/{id}` | `lihi-api.js:173` | ✅ |
