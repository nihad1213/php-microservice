<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Proxy\Upstreams;
use Core\Exceptions\HttpException;
use Core\Http\HttpClient;
use Core\Http\Request;
use Core\Http\Response;

class VerifyToken
{
    public function __construct(private ?Upstreams $upstreams = null, private ?HttpClient $client = null)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw HttpException::unauthorized('Missing bearer token');
        }

        $upstreams = $this->upstreams ?? Upstreams::fromEnv();
        $client = $this->client ?? new HttpClient(3, 1);

        $response = $client->get($upstreams->url('auth', '/auth/verify'), [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
            'X-Request-Id'  => (string) $request->attribute('request_id', ''),
        ]);

        if ($response->status() === 401) {
            throw HttpException::unauthorized('Invalid or expired token');
        }

        $data = $response->json();
        $userId = is_array($data) ? (string) ($data['user_id'] ?? '') : '';

        if (!$response->ok() || preg_match('/^[1-9]\d{0,17}$/', $userId) !== 1) {
            throw HttpException::badGateway('Authentication service returned an unexpected response');
        }

        $request->setAttribute('user_id', (int) $userId);

        return $next($request);
    }
}
