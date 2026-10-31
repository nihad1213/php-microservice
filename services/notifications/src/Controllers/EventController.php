<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationRepository;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Logging\Logger;
use Core\Validation\Validator;

class EventController
{
    private const MESSAGES = [
        'task.created'   => 'Task "%s" was created',
        'task.completed' => 'Task "%s" was completed',
    ];

    private NotificationRepository $notifications;
    private Logger $log;

    public function __construct(?NotificationRepository $notifications = null, ?Logger $log = null)
    {
        $this->notifications = $notifications ?? new NotificationRepository();
        $this->log = $log ?? Logger::forService('notifications', dirname(__DIR__, 2));
    }

    public function store(Request $request): Response
    {
        $event = Validator::validate($request->json(), [
            'type'    => 'required|string|in:' . implode(',', array_keys(self::MESSAGES)),
            'user_id' => 'required|integer|min:1|max:999999999999999999',
            'data'    => 'required|array',
        ]);

        $data = self::validateData($event['data']);
        $userId = (int) $event['user_id'];

        $id = $this->notifications->create(
            $userId,
            $event['type'],
            sprintf(self::MESSAGES[$event['type']], $data['title']),
            $data
        );

        $this->log->info('Event received', ['type' => $event['type'], 'user_id' => $userId, 'notification_id' => $id]);

        return Response::json(['id' => $id, 'type' => $event['type'], 'user_id' => $userId], 201);
    }

    private static function validateData(array $data): array
    {
        $validator = new Validator($data, [
            'task_id' => 'required|integer|min:1',
            'title'   => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $fields = [];

            foreach ($validator->errors() as $field => $message) {
                $fields['data.' . $field] = $message;
            }

            throw HttpException::unprocessable($fields);
        }

        $clean = $validator->validated();

        return ['task_id' => (int) $clean['task_id'], 'title' => trim($clean['title'])];
    }
}