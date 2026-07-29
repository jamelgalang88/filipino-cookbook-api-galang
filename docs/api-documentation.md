# API Documentation

Base URL for local testing:

```text
http://127.0.0.1:8087
```

Add this header to every `/api` request:

```text
Authorization: Bearer YOUR_SECRET_API_TOKEN
```

## GET /health

Checks whether the API is running.

```bash
curl http://127.0.0.1:8087/health
```

Example response:

```json
{"status":"ok"}
```

## GET /api/test

Checks whether the configured bearer token is accepted.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/test
```

## GET /api/foods

Returns all foods with category, origin, instructions, and ingredients.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/foods
```

## GET /api/foods/{id}

Returns one food record by ID.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/foods/1
```

## GET /api/foods/search/{name}

Searches foods by name.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/foods/search/adobo
```

## GET /api/categories

Returns all categories.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/categories
```

## GET /api/origins

Returns all origins.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/origins
```

## GET /api/ingredients

Returns all ingredients.

```bash
curl -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" http://127.0.0.1:8087/api/ingredients
```

## POST /api/foods

Creates a new food record and links it to existing ingredients.

```bash
curl -X POST http://127.0.0.1:8087/api/foods \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"food_name\":\"Sample Dish\",\"category_id\":1,\"origin_id\":4,\"instructions\":\"Cook and serve.\",\"ingredient_ids\":[1,2]}"
```

Required fields:

- `food_name`
- `category_id` or `category_name`
- `origin_id` or `origin_name`
- `instructions`
- `ingredient_ids`, `ingredient_names`, or `ingredients`

## Error Responses

Missing or invalid token:

```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```

Unavailable database:

```json
{
  "status": "error",
  "message": "Database connection is unavailable."
}
```
