<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationRepository;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Validation\Validator;

class NotificationController
{
    private NotificationRepository $notifications;

    public function __construct(?NotificationRepository $notifications = null)
    {
        $this->notifications = $notifications ?? new NotificationRepository();
    }

    public function index(Request $request): Response
    {
        $query = Validator::validate($request->query(), [
            'page'   => 'integer|min:1|max:1000000',
            'limit'  => 'integer|min:1|max:100',
            'unread' => 'boolean',
        ]);

        $userId = self::userId($request);
        $page = self::number($query['page'] ?? null, 1);
        $limit = self::number($query['limit'] ?? null, 20);
        $unreadOnly = in_array($query['unread'] ?? null, ['1', 1, true], true);

        [$rows, $total] = $this->notifications->paginate($userId, $unreadOnly, $page, $limit);

        return Response::json([
            'data' => $rows,
            'meta' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $limit),
                'unread'      => $this->notifications->unreadCount($userId),
            ],
        ]);
    }

    public function read(Request $request, array $params): Response
    {
        $userId = self::userId($request);
        $id = (int) $params['id'];

        if ($this->notifications->find($userId, $id) === null) {
            throw HttpException::notFound('Notification not found');
        }

        $this->notifications->markRead($userId, $id);

        return Response::json($this->notifications->find($userId, $id));
    }

    private static function number(mixed $value, int $default): int
    {
        return $value === null || $value === '' ? $default : (int) $value;
    }

    private static function userId(Request $request): int
    {
        return (int) $request->attribute('user_id');
    }
}