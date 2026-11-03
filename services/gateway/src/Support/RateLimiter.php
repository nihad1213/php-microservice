<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class RateLimiter
{
    public function __construct(private string $directory)
    {
    }

    public static function forService(string $serviceRoot): self
    {
        return new self(rtrim($serviceRoot, '/\\') . '/storage/ratelimit');
    }

    public function hit(string $key, int $limit, int $window, ?int $now = null): array
    {
        $now ??= time();

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create rate limit directory');
        }

        $path = $this->directory . '/' . sha1($key) . '.rl';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open rate limit file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock rate limit file');
            }

            $parts = explode(' ', trim((string) stream_get_contents($handle)));
            $start = (int) ($parts[0] ?? 0);
            $count = (int) ($parts[1] ?? 0);

            if ($start <= 0 || $now - $start >= $window) {
                $start = $now;
                $count = 0;
            }

            $count++;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $start . ' ' . $count);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (random_int(1, 200) === 1) {
            $this->collectGarbage($now);
        }

        return [
            'allowed'     => $count <= $limit,
            'limit'       => $limit,
            'remaining'   => max(0, $limit - $count),
            'retry_after' => max(1, $start + $window - $now),
        ];
    }

    private function collectGarbage(int $now): void
    {
        foreach (glob($this->directory . '/*.rl') ?: [] as $file) {
            $modified = @filemtime($file);

            if ($modified !== false && $now - $modified > 3600) {
                @unlink($file);
            }
        }
    }
}
