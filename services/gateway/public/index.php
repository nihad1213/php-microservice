<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\HealthController;
use App\Controllers\ProxyController;
use App\Middleware\Edge;
use App\Middleware\RateLimit;
use App\Middleware\VerifyToken;
use App\Support\RateLimiter;
use Core\Config\Env;
use Core\Exceptions\ExceptionHandler;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Logging\Logger;

Env::load(__DIR__ . '/../.env');
ExceptionHandler::register(Env::get('APP_ENV') === 'local');

$root = dirname(__DIR__);
$log = Logger::forService('gateway', $root);
$limiter = RateLimiter::forService($root);

$edge = new Edge($log);
$globalLimit = new RateLimit($limiter, 'global', max(1, (int) Env::get('RATE_LIMIT', '120')));
$authLimit = new RateLimit($limiter, 'auth', max(1, (int) Env::get('AUTH_RATE_LIMIT', '10')));

$verifyToken = static fn (Request $request, callable $next): Response => (new VerifyToken())($request, $next);

$router = new Router();
$router->middleware($edge);

$router->get('/health', [HealthController::class, 'index']);
$router->get('/health/services', [HealthController::class, 'services'], [$globalLimit]);

$router->post('/auth/register', [ProxyController::class, 'auth'], [$authLimit]);
$router->post('/auth/login', [ProxyController::class, 'auth'], [$authLimit]);
$router->get('/auth/me', [ProxyController::class, 'auth'], [$globalLimit]);

$router->group('/tasks', [$globalLimit, $verifyToken], function (Router $r) {
    $r->get('/', [ProxyController::class, 'tasks']);
    $r->post('/', [ProxyController::class, 'tasks']);
    $r->get('/{id:\d+}', [ProxyController::class, 'tasks']);
    $r->put('/{id:\d+}', [ProxyController::class, 'tasks']);
    $r->patch('/{id:\d+}/complete', [ProxyController::class, 'tasks']);
    $r->delete('/{id:\d+}', [ProxyController::class, 'tasks']);
});

$router->group('/notifications', [$globalLimit, $verifyToken], function (Router $r) {
    $r->get('/', [ProxyController::class, 'notifications']);
    $r->patch('/{id:\d+}/read', [ProxyController::class, 'notifications']);
});

$router->run();
