<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\RateLimiter;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;

class RateLimit
{
    public function __construct(
        private RateLimiter $limiter,
        private string $bucket,
        private int $limit,
        private int $window = 60
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $result = $this->limiter->hit($this->bucket . '|' . $request->ip(), $this->limit, $this->window);

        if (!$result['allowed']) {
            throw new HttpException(429, 'Too many requests', [
                'retry_after' => $result['retry_after'],
                'limit'       => $result['limit'],
            ]);
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
