<?php
declare(strict_types=1);

namespace Core\Http;

class ClientResponse
{
    public function __construct(
        private int $status,
        private string $body,
        private array $headers
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): mixed
    {
        $data = json_decode($this->body, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}