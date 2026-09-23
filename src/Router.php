<?php
namespace App;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(string $method, string $path): void
    {

        $path = parse_url($path, PHP_URL_PATH);

        $key = "$method $path";

        if (!isset($this->routes[$key])) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        $this->routes[$key]();
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes["$method $path"] = $handler;
    }

}
