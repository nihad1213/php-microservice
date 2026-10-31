<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TaskController;
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

$log = Logger::forService('tasks', dirname(__DIR__));

$requireUser = static fn (Request $request, callable $next): Response => (new RequireUser())($request, $next);

$router = new Router();

$router->get('/health', function () use ($log) {
    $log->debug('health requested');

    $row = Database::fetchOne('SELECT DATABASE() AS name');

    return ['service' => 'tasks', 'status' => 'ok', 'database' => $row['name']];
});

$router->group('/tasks', [$requireUser], function (Router $r) {
    $r->get('/', [TaskController::class, 'index']);
    $r->post('/', [TaskController::class, 'store']);
    $r->get('/{id:\d+}', [TaskController::class, 'show']);
    $r->put('/{id:\d+}', [TaskController::class, 'update']);
    $r->patch('/{id:\d+}/complete', [TaskController::class, 'complete']);
    $r->delete('/{id:\d+}', [TaskController::class, 'destroy']);
});

$router->run();