<?php
declare(strict_types=1);

namespace App\Services;

use Core\Config\Env;
use Core\Http\HttpClient;
use Core\Logging\Logger;
use Throwable;

class NotificationClient
{
    private Logger $log;
    private HttpClient $http;

    public function __construct(?Logger $log = null, ?HttpClient $http = null)
    {
        $this->log = $log ?? Logger::forService('tasks', dirname(__DIR__, 2));
        $this->http = $http ?? new HttpClient(timeout: 2, connectTimeout: 1);
    }

    public function send(string $type, int $userId, array $task): void
    {
        $baseUrl = rtrim((string) Env::get('NOTIFICATIONS_URL', ''), '/');
        $key = (string) Env::get('INTERNAL_KEY', '');

        if ($baseUrl === '' || $key === '') {
            $this->log->warning('Notification skipped: NOTIFICATIONS_URL or INTERNAL_KEY is not set', ['type' => $type]);

            return;
        }

        try {
            $response = $this->http->post(
                $baseUrl . '/events',
                [
                    'type'    => $type,
                    'user_id' => $userId,
                    'data'    => ['task_id' => $task['id'], 'title' => $task['title']],
                ],
                ['X-Internal-Key' => $key]
            );

            if (!$response->ok()) {
                $this->log->warning('Notification service rejected the event', [
                    'type'    => $type,
                    'task_id' => $task['id'],
                    'status'  => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            $this->log->warning('Notification service unavailable', [
                'type'    => $type,
                'task_id' => $task['id'],
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
