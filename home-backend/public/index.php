<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\PdoAlertRepository;
use App\Infrastructure\Persistence\PdoMemberRepository;
use App\Infrastructure\Persistence\PdoTaskRepository;

$envFile = dirname(__DIR__) . '/.env';  
if (is_file($envFile)) {  
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {  
        $line = trim($line);  
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {  
            continue;  
        }  
        [$key, $value] = explode('=', $line, 2);  
        putenv(trim($key) . '=' . trim($value));  
    }  
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '*')));
if (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . ($origin !== '' ? $origin : '*'));
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dsn = getenv('DB_DSN') ?: sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    getenv('DB_HOST'),
    getenv('DB_PORT'),
    getenv('DB_DATABASE'),
    getenv('DB_CHARSET')
);

try {
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'home', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $router = new Router(
        new PdoTaskRepository($pdo),
        new PdoMemberRepository($pdo),
        new PdoAlertRepository($pdo)
    );
    $request = $_SERVER;
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $apiBasePath = rtrim((string) (getenv('API_BASE_PATH') ?: ''), '/');
    if ($apiBasePath !== '' && ($requestPath === $apiBasePath || str_starts_with($requestPath, $apiBasePath . '/'))) {
        $requestPath = substr($requestPath, strlen($apiBasePath)) ?: '/';
    }
    $request['PATH_INFO'] = $requestPath;
    $request['rawBody'] = file_get_contents('php://input') ?: '{}';
    echo json_encode($router->dispatch($request), JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => getenv('APP_DEBUG') === 'true' ? $exception->getMessage() : 'Internal server error']);
}