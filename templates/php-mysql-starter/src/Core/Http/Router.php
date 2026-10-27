<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Http;

final class Router
{
    private const PARAM_PATTERNS = [
        'id' => '([0-9]+)',
        'slug' => '([a-z0-9]+(?:-[a-z0-9]+)*)',
        'lang' => '([a-z]+)',
    ];

    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(Request $request): ?Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];

            foreach ($route['names'] as $index => $name) {
                $params[$name] = $matches[$index + 1];
            }

            return ($route['handler'])($request, $params);
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Methode nicht erlaubt.');
        }

        return null;
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback(
            '/\{([a-z]+)\}/',
            static function (array $match) use (&$names): string {
                $names[] = $match[1];

                return self::PARAM_PATTERNS[$match[1]] ?? '([^/]+)';
            },
            $pattern
        );

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'names' => $names,
            'handler' => $handler,
        ];
    }
}
