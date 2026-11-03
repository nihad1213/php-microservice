<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\EventController;
use App\Controllers\NotificationController;
use App\Middleware\RequireInternalKey;
use App\Middleware\RequireUser;
use Core\Config\Env;
use Core\Database\Database;
use Core\Exceptions\ExceptionHandler;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Logging\Logger;

Env::load(__DIR__ . '/../.env');
ExceptionHandler::register(Env::get('APP_ENV') === 'local');

$log = Logger::forService('notifications', dirname(__DIR__));

$requireInternalKey = static fn (Request $request, callable $next): Response => (new RequireInternalKey())($request, $next);
$requireUser = static fn (Request $request, callable $next): Response => (new RequireUser())($request, $next);

$router = new Router();

$router->get('/health', function () use ($log) {
    $log->debug('health requested');

    $row = Database::fetchOne('SELECT DATABASE() AS name');

    return ['service' => 'notifications', 'status' => 'ok', 'database' => $row['name']];
});

$router->post('/events', [EventController::class, 'store'], [$requireInternalKey]);

$router->group('/notifications', [$requireUser], function (Router $r) {
    $r->get('/', [NotificationController::class, 'index']);
    $r->patch('/{id:\d+}/read', [NotificationController::class, 'read']);
});

$router->run();
