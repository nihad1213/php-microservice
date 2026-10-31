<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Env;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use RuntimeException;

class RequireInternalKey
{
    public function __invoke(Request $request, callable $next): Response
    {
        self::verify($request);

        return $next($request);
    }

    public static function verify(Request $request): void
    {
        $expected = (string) Env::get('INTERNAL_KEY', '');

        if (strlen($expected) < 16) {
            throw new RuntimeException('INTERNAL_KEY must be at least 16 characters long');
        }

        if (!hash_equals($expected, (string) $request->header('x-internal-key', ''))) {
            throw HttpException::unauthorized('Missing or invalid internal key');
        }
    }
}