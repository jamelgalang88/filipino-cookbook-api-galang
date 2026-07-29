<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

function applyCorsHeaders(Response $response, Request $request): Response {
    $origin = $request->getHeaderLine('Origin');
    $allowedOrigin = $origin !== '' ? $origin : '*';

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With')
        ->withHeader('Vary', 'Origin');
}

$app->add(function (Request $request, RequestHandler $handler): Response {
    if ($request->getMethod() === 'OPTIONS') {
        return applyCorsHeaders(new SlimResponse(204), $request);
    }

    $response = $handler->handle($request);
    return applyCorsHeaders($response, $request);
});

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config.example.php';
}

$config = require $configPath;

$host = $config['db_host'] ?? 'localhost';
$dbname = $config['db_name'] ?? 'filipino_cookbook_api';
$user = $config['db_user'] ?? '';
$pass = $config['db_pass'] ?? '';
$apiToken = $config['api_token'] ?? '';

$pdo = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pdo = null;
}

function jsonResponse(Response $response, mixed $data, int $status = 200): Response {
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $response->getBody()->write($payload !== false ? $payload : '{}');

    return $response
        ->withStatus($status)
        ->withHeader('Content-Type', 'application/json');
}

function checkToken(Request $request, string $apiToken): bool
{
    $header = $request->getHeaderLine('Authorization');
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
        return false;
    }

    return $apiToken !== '' && hash_equals($apiToken, trim($matches[1]));
}

function unauthorizedResponse(Response $response): Response
{
    return jsonResponse($response, [
        'status' => 'error',
        'message' => 'Unauthorized access. Valid API token is required.'
    ], 401);
}

function databaseUnavailableResponse(Response $response): Response
{
    return jsonResponse($response, [
        'status' => 'error',
        'message' => 'Database connection is unavailable.'
    ], 503);
}

function firstNonEmptyValue(array $body, array $keys): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
            return $body[$key];
        }
    }

    return null;
}

function resolveLookupId(PDO $pdo, string $table, string $idColumn, string $nameColumn, mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && ctype_digit(trim($value))) {
        return (int) trim($value);
    }

    if (is_array($value)) {
        $value = $value['id'] ?? $value['category_id'] ?? $value['origin_id'] ?? $value['name'] ?? $value['category_name'] ?? $value['origin_name'] ?? null;
    }

    if (!is_scalar($value) && !is_null($value)) {
        $value = null;
    }

    $rawValue = trim((string) $value);
    if ($rawValue === '') {
        return null;
    }

    $query = "SELECT $idColumn FROM $table WHERE CAST($idColumn AS CHAR) = :value OR LOWER($nameColumn) = LOWER(:name) LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'value' => $rawValue,
        'name' => $rawValue,
    ]);

    $resolvedId = $stmt->fetchColumn();
    return $resolvedId !== false ? (int) $resolvedId : null;
}

function resolveIngredientIds(PDO $pdo, mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (is_string($value)) {
        $decodedValue = json_decode($value, true);
        if (is_array($decodedValue)) {
            $value = $decodedValue;
        } else {
            $value = array_values(array_filter(array_map('trim', explode(',', $value))));
        }
    }

    if (!is_array($value)) {
        $value = [$value];
    }

    $resolvedIds = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $candidate = $item['ingredient_id'] ?? $item['id'] ?? $item['name'] ?? $item['ingredient_name'] ?? $item['ingredient'] ?? null;
        } else {
            $candidate = $item;
        }

        if (is_int($candidate) && $candidate > 0) {
            $resolvedIds[] = $candidate;
            continue;
        }

        if (is_string($candidate) && ctype_digit(trim($candidate))) {
            $resolvedIds[] = (int) trim($candidate);
            continue;
        }

        $rawCandidate = trim((string) $candidate);
        if ($rawCandidate === '') {
            continue;
        }

        $stmt = $pdo->prepare('SELECT ingredient_id FROM ingredients WHERE ingredient_id = :value OR LOWER(ingredient_name) = LOWER(:name) LIMIT 1');
        $stmt->execute([
            'value' => $rawCandidate,
            'name' => $rawCandidate,
        ]);

        $resolvedId = $stmt->fetchColumn();
        if ($resolvedId !== false) {
            $resolvedIds[] = (int) $resolvedId;
        }
    }

    return array_values(array_unique($resolvedIds));
}

function normalizeBodyData(mixed $body): array
{
    if (is_object($body)) {
        $body = (array) $body;
    }

    if (!is_array($body)) {
        return [];
    }

    $normalized = [];
    foreach ($body as $key => $value) {
        if (is_array($value) && $key === 'food') {
            $normalized = array_merge($normalized, $value);
        } elseif (is_array($value) && $key === 'data') {
            $normalized = array_merge($normalized, $value);
        } else {
            $normalized[$key] = $value;
        }
    }

    return $normalized;
}

function getRequestBodyData(Request $request): array
{
    $body = $request->getParsedBody();

    if (is_object($body)) {
        $body = (array) $body;
    }

    if (!is_array($body)) {
        $body = [];
    }

    if ($body !== []) {
        return normalizeBodyData($body);
    }

    $stream = $request->getBody();
    if ($stream->isSeekable()) {
        $stream->rewind();
    }

    $rawBody = trim((string) $stream);
    if ($rawBody === '') {
        $rawBody = trim((string) file_get_contents('php://input'));
    }

    if ($rawBody === '') {
        return [];
    }

    $jsonBody = json_decode($rawBody, true);
    if (is_array($jsonBody)) {
        return normalizeBodyData($jsonBody);
    }

    if (str_starts_with($rawBody, '{') || str_starts_with($rawBody, '[')) {
        return [
            '__invalid_json' => json_last_error_msg()
        ];
    }

    if (str_contains($request->getHeaderLine('Content-Type'), 'application/x-www-form-urlencoded')) {
        parse_str($rawBody, $parsedBody);
        return normalizeBodyData($parsedBody);
    }

    return [];
}

function getFoodWithIngredients(PDO $pdo, int $foodId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT f.food_id, f.food_name, f.instructions, c.category_name, o.origin_name
         FROM foods f
         JOIN categories c ON c.category_id = f.category_id
         JOIN origins o ON o.origin_id = f.origin_id
         WHERE f.food_id = :food_id'
    );
    $stmt->execute(['food_id' => $foodId]);
    $food = $stmt->fetch();

    if (!$food) {
        return null;
    }

    $ingredientStmt = $pdo->prepare(
        'SELECT i.ingredient_name
         FROM food_ingredients fi
         JOIN ingredients i ON i.ingredient_id = fi.ingredient_id
         WHERE fi.food_id = :food_id
         ORDER BY i.ingredient_name'
    );
    $ingredientStmt->execute(['food_id' => $foodId]);
    $food['ingredients'] = array_map(fn($row) => $row['ingredient_name'], $ingredientStmt->fetchAll());

    return $food;
}

// Root endpoint: welcome message for the API and token usage guidance.
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note' => 'Use a valid Bearer token to access /api endpoints.'
    ]);
});

// Health check endpoint: confirms the API service is up.
$app->get('/health', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'status' => 'ok'
    ]);
});

// Test endpoint: verifies that the API accepts a valid Bearer token.
$app->get('/api/test', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    return jsonResponse($response, [
        'status' => 'success',
        'message' => 'Request completed successfully'
    ]);
});

// List all foods: returns every food entry with its ingredients and metadata.
$app->get('/api/foods', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $stmt = $pdo->query(
        'SELECT f.food_id, f.food_name, f.instructions, c.category_name, o.origin_name
         FROM foods f
         JOIN categories c ON c.category_id = f.category_id
         JOIN origins o ON o.origin_id = f.origin_id
         ORDER BY f.food_id'
    );

    $foods = $stmt->fetchAll();
    foreach ($foods as &$food) {
        $ingredientStmt = $pdo->prepare(
            'SELECT i.ingredient_name
             FROM food_ingredients fi
             JOIN ingredients i ON i.ingredient_id = fi.ingredient_id
             WHERE fi.food_id = :food_id
             ORDER BY i.ingredient_name'
        );
        $ingredientStmt->execute(['food_id' => $food['food_id']]);
        $food['ingredients'] = array_map(fn($row) => $row['ingredient_name'], $ingredientStmt->fetchAll());
    }
    unset($food);

    return jsonResponse($response, $foods);
});

// Get one food by ID: returns a single food record with its ingredients.
$app->get('/api/foods/{id}', function (Request $request, Response $response, array $args) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $food = getFoodWithIngredients($pdo, (int) $args['id']);
    if ($food === null) {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'Food not found'
        ], 404);
    }

    return jsonResponse($response, $food);
});

// Search foods by name: returns foods whose names match the provided search term.
$app->get('/api/foods/search/{name}', function (Request $request, Response $response, array $args) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $searchTerm = '%' . trim($args['name']) . '%';
    $stmt = $pdo->prepare(
        'SELECT f.food_id, f.food_name, f.instructions, c.category_name, o.origin_name
         FROM foods f
         JOIN categories c ON c.category_id = f.category_id
         JOIN origins o ON o.origin_id = f.origin_id
         WHERE LOWER(f.food_name) LIKE LOWER(:name)
         ORDER BY f.food_name'
    );
    $stmt->execute(['name' => $searchTerm]);
    $foods = $stmt->fetchAll();

    foreach ($foods as &$food) {
        $ingredientStmt = $pdo->prepare(
            'SELECT i.ingredient_name
             FROM food_ingredients fi
             JOIN ingredients i ON i.ingredient_id = fi.ingredient_id
             WHERE fi.food_id = :food_id
             ORDER BY i.ingredient_name'
        );
        $ingredientStmt->execute(['food_id' => $food['food_id']]);
        $food['ingredients'] = array_map(fn($row) => $row['ingredient_name'], $ingredientStmt->fetchAll());
    }
    unset($food);

    return jsonResponse($response, $foods);
});

// List categories: returns all available food categories.
$app->get('/api/categories', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $stmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');

    return jsonResponse($response, $stmt->fetchAll());
});

// List origins: returns all available regional origins.
$app->get('/api/origins', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $stmt = $pdo->query('SELECT origin_id, origin_name FROM origins ORDER BY origin_name');

    return jsonResponse($response, $stmt->fetchAll());
});

// List ingredients: returns all available ingredients from the database.
$app->get('/api/ingredients', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $stmt = $pdo->query('SELECT ingredient_id, ingredient_name FROM ingredients ORDER BY ingredient_name');

    return jsonResponse($response, $stmt->fetchAll());
});

// Create a food entry: accepts new food data and stores it with its ingredients.
$app->post('/api/foods', function (Request $request, Response $response) use ($pdo, $apiToken) {
    if (!checkToken($request, $apiToken)) {
        return unauthorizedResponse($response);
    }

    if ($pdo === null) {
        return databaseUnavailableResponse($response);
    }

    $body = getRequestBodyData($request);

    if (isset($body['__invalid_json'])) {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'Invalid JSON body: ' . $body['__invalid_json']
        ], 400);
    }

    $foodName = trim((string) firstNonEmptyValue($body, ['food_name', 'foodName', 'name', 'recipe_name', 'recipeName']));
    $categoryInput = firstNonEmptyValue($body, ['category_id', 'categoryId', 'category', 'category_name', 'categoryName']);
    $originInput = firstNonEmptyValue($body, ['origin_id', 'originId', 'origin', 'origin_name', 'originName']);
    $instructions = trim((string) firstNonEmptyValue($body, ['instructions', 'instruction', 'steps', 'method', 'description', 'recipe_instructions']));
    $ingredientInput = firstNonEmptyValue($body, ['ingredient_ids', 'ingredients_ids', 'ingredients_id', 'ingredientIds', 'ingredientsIds', 'ingredients', 'ingredient_names', 'ingredientNames']);

    $categoryId = resolveLookupId($pdo, 'categories', 'category_id', 'category_name', $categoryInput);
    $originId = resolveLookupId($pdo, 'origins', 'origin_id', 'origin_name', $originInput);
    $ingredientIds = resolveIngredientIds($pdo, $ingredientInput);

    if ($foodName === '') {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'food_name is required.'
        ], 400);
    }

    if ($instructions === '') {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'instructions is required.'
        ], 400);
    }

    if ($categoryId === null) {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'A valid category_id is required.'
        ], 400);
    }

    if ($originId === null) {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'A valid origin_id is required.'
        ], 400);
    }

    if ($ingredientIds === []) {
        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'At least one valid ingredient_id is required.'
        ], 400);
    }

    $pdo->beginTransaction();

    try {
        $insertFood = $pdo->prepare(
            'INSERT INTO foods (food_name, category_id, origin_id, instructions) VALUES (:food_name, :category_id, :origin_id, :instructions)'
        );
        $insertFood->execute([
            'food_name' => $foodName,
            'category_id' => $categoryId,
            'origin_id' => $originId,
            'instructions' => $instructions,
        ]);

        $foodId = (int) $pdo->lastInsertId();
        $insertIngredient = $pdo->prepare('INSERT INTO food_ingredients (food_id, ingredient_id) VALUES (:food_id, :ingredient_id)');

        foreach ($ingredientIds as $ingredientId) {
            $ingredientId = (int) $ingredientId;
            if ($ingredientId <= 0) {
                continue;
            }

            $ingredientCheck = $pdo->prepare('SELECT 1 FROM ingredients WHERE ingredient_id = :ingredient_id');
            $ingredientCheck->execute(['ingredient_id' => $ingredientId]);
            if ($ingredientCheck->fetch() === false) {
                throw new Exception('Invalid ingredient_id.');
            }

            $insertIngredient->execute([
                'food_id' => $foodId,
                'ingredient_id' => $ingredientId,
            ]);
        }

        $pdo->commit();

        return jsonResponse($response, [
            'status' => 'success',
            'message' => 'Food added successfully.'
        ], 201);
    } catch (Throwable $e) {
        $pdo->rollBack();

        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'Unable to add food.',
            'details' => $e->getMessage()
        ], 500);
    }
});

$app->run();
