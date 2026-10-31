<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Middleware\Authenticate;
use Core\Config\Env;
use Core\Database\Database;
use Core\Exceptions\ExceptionHandler;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Logging\Logger;

Env::load(__DIR__ . '/../.env');
ExceptionHandler::register(Env::get('APP_ENV') === 'local');

$log = Logger::forService('auth', dirname(__DIR__));

$authenticate = static fn (Request $request, callable $next): Response => (new Authenticate())($request, $next);

$router = new Router();

$router->get('/health', function () use ($log) {
    $log->debug('health requested');

    $row = Database::fetchOne('SELECT DATABASE() AS name');

    return ['service' => 'auth', 'status' => 'ok', 'database' => $row['name']];
});

$router->post('/auth/register', [AuthController::class, 'register']);
$router->post('/auth/login', [AuthController::class, 'login']);

$router->group('/auth', [$authenticate], function (Router $r) {
    $r->get('/verify', fn (Request $request) => ['user_id' => $request->attribute('user_id')]);
    $r->get('/me', fn (Request $request) => $request->attribute('user'));
});

$router->run();