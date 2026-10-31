<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserRepository;
use App\Security\Jwt;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Logging\Logger;
use Core\Validation\Validator;
use PDOException;

class AuthController
{
    private const DUMMY_HASH = '$2y$10$K2jjCW4oX5zHNlzINHogLeLCKUKgAHuxgWezpPoxbGc.//BUs3UdC';

    private UserRepository $users;
    private ?Jwt $jwt;
    private Logger $log;

    public function __construct(?UserRepository $users = null, ?Jwt $jwt = null, ?Logger $log = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->jwt = $jwt;
        $this->log = $log ?? Logger::forService('auth', dirname(__DIR__, 2));
    }

    public function register(Request $request): Response
    {
        $data = Validator::validate(self::input($request), [
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:72',
        ]);

        if (strlen($data['password']) > 72) {
            throw HttpException::unprocessable(['password' => 'The password must not be longer than 72 bytes.']);
        }

        $email = self::normalizeEmail($data['email']);

        try {
            $id = $this->users->create($email, password_hash($data['password'], PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw HttpException::conflict('Email is already registered');
            }

            throw $e;
        }

        $this->log->info('User registered', ['user_id' => $id]);

        return Response::json(['id' => $id, 'email' => $email], 201);
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate(self::input($request), [
            'email'    => 'required|string|max:255',
            'password' => 'required|string|max:1024',
        ]);

        $user = $this->users->findByEmail(self::normalizeEmail($data['email']));

        $passwordIsCorrect = password_verify($data['password'], $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || !$passwordIsCorrect) {
            $this->log->warning('Login failed', ['ip' => $request->ip()]);

            throw HttpException::unauthorized('Invalid credentials');
        }

        $jwt = $this->jwt ?? Jwt::fromEnv();

        $this->log->info('User logged in', ['user_id' => $user['id']]);

        return Response::json([
            'token'      => $jwt->issue($user['id']),
            'token_type' => 'Bearer',
            'expires_in' => $jwt->ttl(),
        ]);
    }

    private static function input(Request $request): array
    {
        $input = $request->json();

        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = trim($input['email']);
        }

        return $input;
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}