<?php
declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Env;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use RuntimeException;

class RequireUser
{
    public function __invoke(Request $request, callable $next): Response
    {
        $expected = (string) Env::get('INTERNAL_KEY', '');

        if (strlen($expected) < 16) {
            throw new RuntimeException('INTERNAL_KEY must be at least 16 characters long');
        }

        if (!hash_equals($expected, (string) $request->header('x-internal-key', ''))) {
            throw HttpException::unauthorized('Missing or invalid internal key');
        }

        $userId = (string) $request->header('x-user-id', '');

        if (preg_match('/^[1-9]\d{0,17}$/', $userId) !== 1) {
            throw HttpException::unauthorized('Missing or invalid user id');
        }

        $request->setAttribute('user_id', (int) $userId);

        return $next($request);
    }
}
