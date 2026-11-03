<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\UserRepository;
use App\Security\Jwt;
use Core\Exceptions\HttpException;
use Core\Http\Request;
use Core\Http\Response;

class Authenticate
{
    public function __construct(private ?Jwt $jwt = null, private ?UserRepository $users = null)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw HttpException::unauthorized('Missing bearer token');
        }

        $jwt = $this->jwt ?? Jwt::fromEnv();
        $users = $this->users ?? new UserRepository();

        $user = $users->findById($jwt->verify($token));
        if ($user === null) {
            throw HttpException::unauthorized('Invalid or expired token');
        }

        $request->setAttribute('user_id', $user['id']);
        $request->setAttribute('user', $user);

        return $next($request);
    }
}
