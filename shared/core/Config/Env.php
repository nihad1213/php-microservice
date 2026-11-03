<?php
declare(strict_types=1);

namespace Core\Config;

class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $parsed = self::parseLine($line);
            if ($parsed === null) {
                continue;
            }

            [$key, $value] = $parsed;

            if (self::get($key) !== null) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false ? $default : (string) $value;
    }

    private static function parseLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            return null;
        }

        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            return null;
        }

        $key = trim(substr($line, 0, $pos));
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            return null;
        }

        $value = trim(substr($line, $pos + 1));

        return [$key, self::parseValue($value)];
    }

    private static function parseValue(string $value): string
    {
        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $m) === 1) {
            return strtr($m[1], ['\\n' => "\n", '\\"' => '"', '\\\\' => '\\']);
        }

        if (preg_match("/^'([^']*)'/", $value, $m) === 1) {
            return $m[1];
        }

        return rtrim((string) preg_replace('/\s+#.*$/', '', $value));
    }
}
