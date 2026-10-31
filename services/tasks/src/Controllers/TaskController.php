<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TaskRepository;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Logging\Logger;
use Core\Validation\Validator;

class TaskController
{
    private TaskRepository $tasks;
    private Logger $log;

    public function __construct(?TaskRepository $tasks = null, ?Logger $log = null)
    {
        $this->tasks = $tasks ?? new TaskRepository();
        $this->log = $log ?? Logger::forService('tasks', dirname(__DIR__, 2));
    }

    public function index(Request $request): Response
    {
        $query = Validator::validate($request->query(), [
            'page'   => 'integer|min:1|max:1000000',
            'limit'  => 'integer|min:1|max:100',
            'status' => 'in:todo,doing,done',
        ]);

        $page = self::number($query['page'] ?? null, 1);
        $limit = self::number($query['limit'] ?? null, 20);
        $status = ($query['status'] ?? '') === '' ? null : $query['status'];

        [$rows, $total] = $this->tasks->paginate(self::userId($request), $status, $page, $limit);

        return Response::json([
            'data' => $rows,
            'meta' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $userId = self::userId($request);

        $id = $this->tasks->create($userId, self::input($request, false));

        $this->log->info('Task created', ['user_id' => $userId, 'task_id' => $id]);

        return Response::json($this->tasks->find($userId, $id), 201);
    }

    public function show(Request $request, array $params): Response
    {
        return Response::json($this->findOrFail($request, $params));
    }

    public function update(Request $request, array $params): Response
    {
        $userId = self::userId($request);
        $task = $this->findOrFail($request, $params);

        $data = self::input($request, true);

        if ($data === []) {
            throw HttpException::unprocessable(['body' => 'Send at least one of: title, description, status, due_date.']);
        }

        $this->tasks->update($userId, $task['id'], $data);

        $this->log->info('Task updated', ['user_id' => $userId, 'task_id' => $task['id']]);

        return Response::json($this->tasks->find($userId, $task['id']));
    }

    public function complete(Request $request, array $params): Response
    {
        $userId = self::userId($request);
        $task = $this->findOrFail($request, $params);

        $this->tasks->update($userId, $task['id'], ['status' => 'done']);

        $this->log->info('Task completed', ['user_id' => $userId, 'task_id' => $task['id']]);

        return Response::json($this->tasks->find($userId, $task['id']));
    }

    public function destroy(Request $request, array $params): Response
    {
        $userId = self::userId($request);

        if (!$this->tasks->delete($userId, (int) $params['id'])) {
            throw HttpException::notFound('Task not found');
        }

        $this->log->info('Task deleted', ['user_id' => $userId, 'task_id' => (int) $params['id']]);

        return Response::noContent();
    }

    private function findOrFail(Request $request, array $params): array
    {
        $task = $this->tasks->find(self::userId($request), (int) $params['id']);

        if ($task === null) {
            throw HttpException::notFound('Task not found');
        }

        return $task;
    }

    private static function input(Request $request, bool $partial): array
    {
        $input = $request->json();

        $rules = [
            'title'       => 'string|max:255',
            'description' => 'string|max:5000',
            'status'      => 'in:todo,doing,done',
            'due_date'    => 'date',
        ];

        if (!$partial || array_key_exists('title', $input)) {
            $rules['title'] = 'required|' . $rules['title'];
        }

        if ($partial && array_key_exists('status', $input)) {
            $rules['status'] = 'required|' . $rules['status'];
        }

        $data = Validator::validate($input, $rules);

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        foreach (['description', 'due_date'] as $field) {
            if (array_key_exists($field, $data) && trim((string) $data[$field]) === '') {
                $data[$field] = null;
            }
        }

        return $data;
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