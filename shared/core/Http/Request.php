<?php

declare(strict_types=1);

namespace Core\Http;

class Request
{
    public function __construct(
        public readonly array $get,
        public readonly array $post,
        public readonly array $server,
        public readonly array $cookies
    ){}

    public static function createFromGlobals(): self 
    {
        return new self(
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKE ?? []
        );
    }

    public function input(string $key, mixed $default = null): mixed 
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function getMethod(): string 
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string 
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH);
    }
}