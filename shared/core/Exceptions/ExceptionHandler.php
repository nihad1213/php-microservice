<?php
declare(strict_types=1);

namespace Core\Exceptions;

use ErrorException;
use Throwable;

class ExceptionHandler
{
    private static bool $debug = false;

    public static function register(bool $debug = false): void
    {
        self::$debug = $debug;

        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity) || ($severity & (E_DEPRECATED | E_USER_DEPRECATED))) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): void
    {
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $body   = ['error' => $e->getMessage(), 'code' => $status] + $e->getExtra();
        } else {
            $status = 500;
            $body   = ['error' => 'Internal Server Error', 'code' => 500];
        }

        if ($status >= 500) {
            error_log(sprintf(
                '%s: %s in %s:%d%s%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString()
            ));

            if (self::$debug) {
                $body['debug'] = [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => explode(PHP_EOL, $e->getTraceAsString()),
                ];
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: '{"error":"Internal Server Error","code":500}';
    }
}
