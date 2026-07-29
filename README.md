# Filipino Cookbook API

A secured PHP Slim API for browsing Filipino foods, categories, origins, and ingredients.

## Requirements

- PHP 8.0 or newer
- Composer
- MySQL or MariaDB
- Apache/XAMPP or PHP built-in server

## Installation

1. Clone the repository into your local server folder.
2. Run Composer:

   ```bash
   composer install
   ```

3. Import the database:

   ```bash
   mysql -u root -p < database.sql
   ```

4. Copy the example configuration:

   ```bash
   copy config.example.php config.php
   ```

5. Edit `config.php` and set your local database username, password, and API token.

   ```php
   return [
       'db_host' => 'localhost',
       'db_name' => 'filipino_cookbook_api',
       'db_user' => 'YOUR_DATABASE_USERNAME',
       'db_pass' => 'YOUR_DATABASE_PASSWORD',
       'api_token' => 'YOUR_SECRET_API_TOKEN',
   ];
   ```

6. Start Apache and MySQL in XAMPP, or run the built-in PHP server:

   ```bash
   php -S localhost:8000 -t public
   ```

7. Test the API health endpoint:

   ```bash
   https://localhost:8000/api/health
   ```

## Authentication

All `/api` endpoints require a bearer token:

```bash
Authorization: Bearer YOUR_SECRET_API_TOKEN
```

The real `config.php` file is ignored by Git. Do not commit database passwords or private tokens.

## Main Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/` | API welcome message |
| GET | `/health` | Service health check |
| GET | `/api/test` | Authenticated test endpoint |
| GET | `/api/foods` | List all foods with ingredients |
| GET | `/api/foods/{id}` | Get one food by ID |
| GET | `/api/foods/search/{name}` | Search foods by name |
| GET | `/api/categories` | List categories |
| GET | `/api/origins` | List origins |
| GET | `/api/ingredients` | List ingredients |
| POST | `/api/foods` | Add a food entry |

See [docs/api-documentation.md](docs/api-documentation.md) for request examples and response samples.

## Endpoint Test Evidence

Successful endpoint JSON outputs are saved in [docs/endpoint-results](docs/endpoint-results).

Screenshots are saved in [docs/screenshots](docs/screenshots):

- `api-test.png`
- `api-foods.png`
- `api-foods-1.png`
- `api-categories.png`
- `api-origins.png`
- `api-ingredients.png`

## Repository Safety Checklist

- `config.php` is ignored.
- `vendor/` is ignored and should be recreated with `composer install`.
- `config.example.php` contains placeholder values only.
- No private database password, API key, personal access token, or server credential is required in the repository.
