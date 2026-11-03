<?php
declare(strict_types=1);

namespace Core\Logging;

use Core\Config\Env;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

class Logger
{
    private const LEVELS = ['debug' => 100, 'info' => 200, 'warning' => 300, 'error' => 400];

    private int $threshold;
    private bool $warned = false;

    public function __construct(
        private string $channel,
        private string $destination,
        string $minLevel = 'info',
        private array $context = []
    ) {
        $this->threshold = self::levelValue($minLevel);
    }

    public static function forService(string $channel, string $serviceRoot): self
    {
        $destination = Env::get('LOG_DESTINATION')
            ?? rtrim($serviceRoot, '/\\') . '/storage/logs/app.log';

        return new self($channel, $destination, Env::get('LOG_LEVEL') ?? 'info');
    }

    public function withContext(array $context): self
    {
        $copy = clone $this;
        $copy->context = $context + $this->context;

        return $copy;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (self::levelValue($level) < $this->threshold) {
            return;
        }

        $context = $context + $this->context;

        $line = sprintf(
            '%s %s.%s: %s%s%s',
            (new DateTimeImmutable())->format('c'),
            $this->channel,
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message),
            $context === [] ? '' : ' ' . $this->encode($context),
            PHP_EOL
        );

        $this->write($line);
    }

    private static function levelValue(string $level): int
    {
        $level = strtolower($level);

        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(
                "Unknown log level \"{$level}\". Use: " . implode(', ', array_keys(self::LEVELS))
            );
        }

        return self::LEVELS[$level];
    }

    private function write(string $line): void
    {
        $isStream = str_starts_with($this->destination, 'php://');

        if (!$isStream) {
            $dir = dirname($this->destination);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        $flags = $isStream ? FILE_APPEND : FILE_APPEND | LOCK_EX;

        if (@file_put_contents($this->destination, $line, $flags) === false) {
            if (!$this->warned) {
                $this->warned = true;
                error_log("Logger: cannot write to {$this->destination} (does the folder exist and can PHP write to it?)");
            }

            error_log(rtrim($line));
        }
    }

    private function encode(array $context): string
    {
        $json = json_encode(
            $this->normalize($context),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $json === false ? '{}' : $json;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return [
                'class'   => get_class($value),
                'message' => $value->getMessage(),
                'at'      => $value->getFile() . ':' . $value->getLine(),
            ];
        }

        if (is_array($value)) {
            return array_map([$this, 'normalize'], $value);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_resource($value)) {
            return 'resource';
        }

        return $value;
    }
}
