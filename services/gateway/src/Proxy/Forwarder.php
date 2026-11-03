<?php
declare(strict_types=1);

namespace App\Proxy;

use Core\Config\Env;
use Core\Exceptions\HttpException;
use Core\Http\HttpClient;
use Core\Http\Request;
use Core\Http\Response;
use RuntimeException;

class Forwarder
{
    private const MAX_BODY = 1048576;

    public function __construct(
        private Upstreams $upstreams,
        private HttpClient $client,
        private string $internalKey
    ) {
        if (strlen($internalKey) < 16) {
            throw new RuntimeException('INTERNAL_KEY must be at least 16 characters long');
        }
    }

    public static function fromEnv(): self
    {
        $timeout = max(1, (int) Env::get('UPSTREAM_TIMEOUT', '10'));

        return new self(
            Upstreams::fromEnv(),
            new HttpClient($timeout, 2),
            (string) Env::get('INTERNAL_KEY', '')
        );
    }

    public function forward(string $service, Request $request, bool $asUser, bool $passAuthorization = false): Response
    {
        if (strlen($request->body()) > self::MAX_BODY) {
            throw new HttpException(413, 'Request body too large');
        }

        $path = $request->path();
        $query = $request->query();
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept'       => $request->header('accept', 'application/json'),
            'X-Request-Id' => (string) $request->attribute('request_id', ''),
        ];

        if ($request->body() !== '') {
            $headers['Content-Type'] = $request->header('content-type', 'application/json');
        }

        if ($passAuthorization && $request->header('authorization') !== null) {
            $headers['Authorization'] = $request->header('authorization');
        }

        if ($asUser) {
            $headers['X-Internal-Key'] = $this->internalKey;
            $headers['X-User-Id'] = (string) $request->attribute('user_id', '');
        }

        $body = $request->body() === '' ? null : $request->body();

        $upstream = $this->client->request(
            $request->method(),
            $this->upstreams->url($service, $path),
            $body,
            $headers
        );

        if ($asUser && $upstream->status() === 401) {
            throw HttpException::badGateway('Upstream service rejected the gateway credentials');
        }

        $out = [];
        foreach (['content-type' => 'Content-Type', 'retry-after' => 'Retry-After'] as $from => $to) {
            $value = $upstream->header($from);
            if ($value !== null) {
                $out[$to] = $value;
            }
        }

        return Response::raw($upstream->body(), $upstream->status(), $out);
    }
}
