<?php

declare(strict_types=1);

namespace Core\Exceptions;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    private const PHRASES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

     public function __construct(private int $statusCode, string $message = '', private array $extra = [],
        ?Throwable $previous = null
    ) {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new InvalidArgumentException("Invalid HTTP error status: {$statusCode}");
        }
 
        parent::__construct(
            $message !== '' ? $message : (self::PHRASES[$statusCode] ?? 'Error'),
            $statusCode,
            $previous
        );
    }
 
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
 
    public function getExtra(): array
    {
        return $this->extra;
    }
 
 
    public static function badRequest(string $message = ''): self
    {
        return new self(400, $message);
    }
 
    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }
 
    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }
 
    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }
 
    public static function methodNotAllowed(string $message = ''): self
    {
        return new self(405, $message);
    }
 
    public static function conflict(string $message = ''): self
    {
        return new self(409, $message);
    }
 
    public static function unprocessable(array $fields, string $message = 'Validation failed'): self
    {
        return new self(422, $message, ['fields' => $fields]);
    }
 
    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message);
    }
 
    public static function badGateway(string $message = ''): self
    {
        return new self(502, $message);
    }
 
    public static function gatewayTimeout(string $message = ''): self
    {
        return new self(504, $message);
    }
}