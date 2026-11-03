<?php
declare(strict_types=1);

namespace Core\Http;

class Response
{
    private function __construct(
        private int $status,
        private string $body,
        private array $headers
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return new self($status, $body, $headers + ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self(204, '', []);
    }

    public static function raw(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $copy = clone $this;
        $copy->headers[$name] = $value;

        return $copy;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . str_replace(["\r", "\n"], '', (string) $value));
            }

            $hasContentType = isset(array_change_key_case($this->headers, CASE_LOWER)['content-type']);

            if ($this->body === '' && !$hasContentType) {
                ini_set('default_mimetype', '');
            }
        }

        echo $this->body;
    }
}
