# Filipino Cookbook API Documentation

This file contains the same technical API reference required for repository submission. For full installation, setup, endpoint, testing, and developer information, see [README.md](README.md).

## API Title

Filipino Cookbook API

## Base URL

Using the PHP built-in server:

```text
http://localhost:8000
```

Using Apache/XAMPP:

```text
http://localhost/filipino-cookbook-api-galang/public
```

## Authentication

All `/api` routes require a bearer token.

```text
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

The token is configured locally in `config.php`. Only `config.example.php` should be uploaded to GitHub.

## Endpoints

| Method | Endpoint | Description | Auth Required |
| --- | --- | --- | --- |
| GET | `/` | API welcome message | No |
| GET | `/health` | API health check | No |
| GET | `/api/test` | Test authentication and database connection | Yes |
| GET | `/api/foods` | Get all foods | Yes |
| GET | `/api/foods/{id}` | Get one food by ID | Yes |
| GET | `/api/foods/search/{name}` | Search foods by name | Yes |
| GET | `/api/categories` | Get all categories | Yes |
| GET | `/api/origins` | Get all origins | Yes |
| GET | `/api/ingredients` | Get all ingredients | Yes |
| POST | `/api/foods` | Add a new food | Yes |

## Example Requests

```text
GET http://localhost:8000/api/foods
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

```text
GET http://localhost:8000/api/foods/1
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

```text
POST http://localhost:8000/api/foods
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

```json
{
  "food_name": "Sample Dish",
  "category_id": 1,
  "origin_id": 4,
  "instructions": "Cook and serve.",
  "ingredient_ids": [1, 2]
}
```

## Example Responses

Successful food response:

```json
{
  "food_id": "1",
  "food_name": "Adobo",
  "instructions": "Marinate the meat with soy sauce, vinegar, garlic, bay leaves, and peppercorn. Simmer until the meat becomes tender and the sauce is reduced.",
  "category_name": "Main Dish",
  "origin_name": "Philippines",
  "ingredients": [
    "Bay leaves",
    "Chicken or pork",
    "Cooking oil",
    "Garlic",
    "Peppercorn",
    "Soy sauce",
    "Vinegar"
  ]
}
```

Unauthorized response:

```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```

Food not found response:

```json
{
  "status": "error",
  "message": "Food not found"
}
```

Database unavailable response:

```json
{
  "status": "error",
  "message": "Database connection is unavailable."
}
```

## HTTP Status Codes

| Status Code | Meaning |
| --- | --- |
| 200 | Request completed successfully |
| 201 | Resource created successfully |
| 400 | Invalid request or parameter |
| 401 | Missing or invalid authentication |
| 403 | Access is not allowed |
| 404 | Requested resource was not found |
| 429 | Too many requests |
| 500 | Internal server error |
| 503 | Database connection is unavailable |

## Database

Database name:

```text
filipino_cookbook_api
```

SQL file:

```text
database.sql
```

Tables:

- `categories`
- `origins`
- `ingredients`
- `foods`
- `food_ingredients`

Relationships:

```text
categories -> foods <- origins
foods -> food_ingredients <- ingredients
```

## Testing Evidence

Successful JSON response files:

```text
docs/endpoint-results/
```

Successful endpoint screenshots:

```text
docs/screenshots/
```

See the README for screenshot captions and full setup instructions.
