<?php
declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Env;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Logging\Logger;

class Edge
{
    public function __construct(private Logger $log, private ?array $allowedOrigins = null)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $started = microtime(true);

        $incoming = (string) $request->header('x-request-id', '');
        $requestId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) === 1 ? $incoming : bin2hex(random_bytes(8));
        $request->setAttribute('request_id', $requestId);

        $origin = (string) $request->header('origin', '');
        $corsHeaders = $this->corsHeaders($origin);

        if ($request->method() === 'OPTIONS' && $origin !== '') {
            return $this->finish(
                $request,
                Response::raw('', 204, $corsHeaders + [
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, X-Request-Id',
                    'Access-Control-Max-Age'       => '600',
                ]),
                $requestId,
                $started
            );
        }

        try {
            $response = $next($request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($e);
        }

        foreach ($corsHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $this->finish($request, $response, $requestId, $started);
    }

    private function finish(Request $request, Response $response, string $requestId, float $started): Response
    {
        $this->log->info('request', [
            'request_id' => $requestId,
            'method'     => $request->method(),
            'path'       => $request->path(),
            'status'     => $response->status(),
            'ms'         => (int) round((microtime(true) - $started) * 1000),
            'user_id'    => $request->attribute('user_id'),
            'ip'         => $request->ip(),
        ]);

        return $response
            ->withHeader('X-Request-Id', $requestId)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Cache-Control', 'no-store');
    }

    private function errorResponse(HttpException $e): Response
    {
        $extra = $e->getExtra();
        $headers = [];

        if (isset($extra['allowed']) && is_array($extra['allowed'])) {
            $headers['Allow'] = implode(', ', $extra['allowed']);
        }

        if (isset($extra['retry_after'])) {
            $headers['Retry-After'] = (string) $extra['retry_after'];
        }

        return Response::json(
            ['error' => $e->getMessage(), 'code' => $e->getStatusCode()] + $extra,
            $e->getStatusCode(),
            $headers
        );
    }

    private function corsHeaders(string $origin): array
    {
        if ($origin === '') {
            return [];
        }

        $allowed = $this->allowedOrigins ?? array_values(array_filter(array_map(
            'trim',
            explode(',', (string) Env::get('ALLOWED_ORIGINS', ''))
        )));

        if (!in_array($origin, $allowed, true)) {
            return [];
        }

        return ['Access-Control-Allow-Origin' => $origin, 'Vary' => 'Origin'];
    }
}
