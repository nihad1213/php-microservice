<?php

declare(strict_types=1);

namespace App\Security;

use Core\Config\Env;
use Core\Exceptions\HttpException;
use RuntimeException;

class Jwt
{
    private const ISSUER = 'auth-service';

    public function __construct(private string $secret, private int $ttl = 3600)
    {
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be at least 32 characters long');
        }

        if ($ttl < 1) {
            throw new RuntimeException('JWT_TTL must be a positive number of seconds');
        }
    }

    public static function fromEnv(): self
    {
        return new self((string) Env::get('JWT_SECRET', ''), (int) Env::get('JWT_TTL', '3600'));
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function issue(int $userId, ?int $now = null): string
    {
        $now ??= time();

        $signingInput = self::encode(['alg' => 'HS256', 'typ' => 'JWT'])
            . '.'
            . self::encode([
                'iss' => self::ISSUER,
                'sub' => (string) $userId,
                'iat' => $now,
                'exp' => $now + $this->ttl,
            ]);

        return $signingInput . '.' . self::base64UrlEncode($this->sign($signingInput));
    }

    public function verify(string $token, ?int $now = null): int
    {
        $now ??= time();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw self::invalid();
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = self::decodeJson($encodedHeader);
        $signature = self::base64UrlDecode($encodedSignature);

        if ($header === null || $signature === null || ($header['alg'] ?? null) !== 'HS256') {
            throw self::invalid();
        }

        if (!hash_equals($this->sign($encodedHeader . '.' . $encodedPayload), $signature)) {
            throw self::invalid();
        }

        $payload = self::decodeJson($encodedPayload);
        if ($payload === null || ($payload['iss'] ?? null) !== self::ISSUER) {
            throw self::invalid();
        }

        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) || $exp <= $now) {
            throw self::invalid();
        }

        $sub = $payload['sub'] ?? null;
        if (!is_string($sub) || preg_match('/^[1-9]\d{0,18}$/', $sub) !== 1) {
            throw self::invalid();
        }

        return (int) $sub;
    }

    private function sign(string $input): string
    {
        return hash_hmac('sha256', $input, $this->secret, true);
    }

    private static function invalid(): HttpException
    {
        return HttpException::unauthorized('Invalid or expired token');
    }

    private static function encode(array $data): string
    {
        return self::base64UrlEncode(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function decodeJson(string $segment): ?array
    {
        $json = self::base64UrlDecode($segment);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $data) !== 1) {
            return null;
        }

        $padded = str_pad(strtr($data, '-_', '+/'), (int) (ceil(strlen($data) / 4) * 4), '=');
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}