<?php
declare(strict_types=1);

namespace App\Middleware;

use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;

class RequireUser
{
    public function __invoke(Request $request, callable $next): Response
    {
        RequireInternalKey::verify($request);

        $userId = (string) $request->header('x-user-id', '');

        if (preg_match('/^[1-9]\d{0,17}$/', $userId) !== 1) {
            throw HttpException::unauthorized('Missing or invalid user id');
        }

        $request->setAttribute('user_id', (int) $userId);

        return $next($request);
    }
}
