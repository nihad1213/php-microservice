<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Proxy\Upstreams;
use Core\Http\HttpClient;
use Core\Http\Response;
use Throwable;

class HealthController
{
    public function __construct(private ?Upstreams $upstreams = null, private ?HttpClient $client = null)
    {
    }

    public function index(): array
    {
        return ['service' => 'gateway', 'status' => 'ok'];
    }

    public function services(): Response
    {
        $upstreams = $this->upstreams ?? Upstreams::fromEnv();
        $client = $this->client ?? new HttpClient(2, 1);

        $services = [];
        $healthy = true;

        foreach ($upstreams->services() as $name) {
            $started = microtime(true);

            try {
                $response = $client->get($upstreams->url($name, '/health'), ['Accept' => 'application/json']);
                $up = $response->ok();
            } catch (Throwable) {
                $up = false;
            }

            $healthy = $healthy && $up;
            $services[$name] = [
                'status' => $up ? 'up' : 'down',
                'ms'     => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        return Response::json(
            ['service' => 'gateway', 'status' => $healthy ? 'ok' : 'degraded', 'services' => $services],
            $healthy ? 200 : 503
        );
    }
}
