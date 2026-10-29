<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Exceptions\HttpException;
use JsonException;

class Request
{
    private array $headers = [];
    private array $attributes = [];
    private ?array $json = null;

    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        array $headers = [],
        private string $body = '',
        private string $ip = ''
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);

        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public static function capture(): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $body = file_get_contents('php://input');

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            is_string($path) ? $path : '/',
            $_GET,
            $headers,
            $body === false ? '' : $body,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        if (trim($this->body) === '') {
            return $this->json = [];
        }

        try {
            $data = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw HttpException::badRequest('Invalid JSON body');
        }

        if (!is_array($data)) {
            throw HttpException::badRequest('JSON body must be an object or an array');
        }

        return $this->json = $data;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if ($header === null || preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private static function normalizePath(string $path): string
    {
        return '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }
}