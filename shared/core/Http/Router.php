<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Exceptions\HttpException;
use InvalidArgumentException;
use RuntimeException;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $groupMiddleware = [];
    private string $prefix = '';

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, mixed $handler, array $middleware = []): self
    {
        if (!self::isHandler($handler)) {
            throw new InvalidArgumentException('Route handler must be a callable or [ControllerClass::class, \'method\']');
        }

        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => self::compile(self::normalize($this->prefix . '/' . $path)),
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];

        return $this;
    }

    public function middleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = rtrim($previousPrefix, '/') . '/' . trim($prefix, '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        try {
            $routes($this);
        } finally {
            $this->prefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    public function run(?Request $request = null): void
    {
        $this->dispatch($request ?? Request::capture())->send();
    }

    public function dispatch(Request $request): Response
    {
        $pipeline = $this->pipeline(
            $this->globalMiddleware,
            fn (Request $request): Response => $this->dispatchRoute($request)
        );

        return $pipeline($request);
    }

    private function dispatchRoute(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = rawurldecode($value);
                }
            }

            $pipeline = $this->pipeline(
                $route['middleware'],
                fn (Request $request): Response => $this->toResponse($this->call($route['handler'], $request, $params))
            );

            return $pipeline($request);
        }

        if ($allowed !== []) {
            throw new HttpException(405, '', ['allowed' => array_values(array_unique($allowed))]);
        }

        throw HttpException::notFound();
    }

    private function pipeline(array $middleware, callable $core): callable
    {
        return array_reduce(
            array_reverse($middleware),
            fn (callable $next, callable $layer): callable =>
                fn (Request $request): Response => $this->toResponse($layer($request, $next)),
            $core
        );
    }

    private function call(mixed $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && is_string($handler[0]) && !is_callable($handler)) {
            $handler = [new $handler[0](), $handler[1]];
        }

        return $handler($request, $params);
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return Response::noContent();
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        throw new RuntimeException('A handler must return a Response, an array or null');
    }

    private static function isHandler(mixed $handler): bool
    {
        return is_callable($handler)
            || (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1]));
    }

    private static function normalize(string $path): string
    {
        return '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }

    private static function compile(string $path): string
    {
        $parts = preg_split('#(\{\w+(?::[^}]+)?\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';

        foreach ($parts as $part) {
            if (preg_match('#^\{(\w+)(?::([^}]+))?\}$#', $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }
}