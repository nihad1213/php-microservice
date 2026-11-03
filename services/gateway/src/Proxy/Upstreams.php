<?php
declare(strict_types=1);

namespace App\Proxy;

use Core\Config\Env;
use RuntimeException;

class Upstreams
{
    private const ENV_KEYS = [
        'auth'          => 'AUTH_URL',
        'tasks'         => 'TASKS_URL',
        'notifications' => 'NOTIFICATIONS_URL',
    ];

    public function __construct(private array $urls)
    {
    }

    public static function fromEnv(): self
    {
        $urls = [];

        foreach (self::ENV_KEYS as $service => $key) {
            $url = rtrim((string) Env::get($key, ''), '/');

            if (preg_match('#^https?://[^/\s]+$#', $url) !== 1) {
                throw new RuntimeException($key . ' must be a base URL such as http://nginx:8001');
            }

            $urls[$service] = $url;
        }

        return new self($urls);
    }

    public function services(): array
    {
        return array_keys($this->urls);
    }

    public function url(string $service, string $path): string
    {
        if (!isset($this->urls[$service])) {
            throw new RuntimeException('Unknown upstream service: ' . $service);
        }

        return $this->urls[$service] . '/' . ltrim($path, '/');
    }
}
