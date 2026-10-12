<?php

namespace Tests;

use App\Database;
use Dotenv\Dotenv;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected static PDO $pdo;
    private static $server;

    public static function setUpBeforeClass(): void
    {
        Dotenv::createImmutable(__DIR__ . '/..', '.env.testing')->load();
        self::$pdo = Database::connect();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        proc_close(self::$server);
    }

    private static function startServer(): void
    {
        $nowhere = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8001', '-t', __DIR__ . '/../public'],
            [1 => ['file', $nowhere, 'w'], 2 => ['file', $nowhere, 'w']],
            $pipes
        );

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', 8001);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }

        self::fail('Test server did not start');
    }

    protected function setUp(): void
    {
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        self::$pdo->exec('TRUNCATE TABLE transactions');
        self::$pdo->exec('TRUNCATE TABLE categories');
        self::$pdo->exec('TRUNCATE TABLE users');
        self::$pdo->exec('TRUNCATE TABLE sessions');
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function postRaw(string $path, string $body, array $extraHeaders = []): array
    {
        return $this->send('POST', $path, $body, $extraHeaders);
    }

    protected function post(string $path, array $body, array $extraHeaders = []): array
    {
        return $this->postRaw($path, json_encode($body), $extraHeaders);
    }

    protected function putRaw(string $path, string $body, array $extraHeaders = []): array
    {
        return $this->send('PUT', $path, $body, $extraHeaders);
    }

    protected function put(string $path, array $body, array $extraHeaders = []): array
    {
        return $this->putRaw($path, json_encode($body), $extraHeaders);
    }

    protected function get(string $path, array $extraHeaders = []): array
    {
        return $this->send('GET', $path, null, $extraHeaders);
    }

    protected function delete(string $path, array $extraHeaders = []): array
    {
        return $this->send('DELETE', $path, null, $extraHeaders);
    }

    private function send(string $method, string $path, ?string $body, array $extraHeaders): array
    {
        $http = [
            'method' => $method,
            'header' => $extraHeaders,
            'ignore_errors' => true,
        ];

        if ($body !== null) {
            $http['header'] = array_merge(['Content-Type: application/json'], $extraHeaders);
            $http['content'] = $body;
        }

        $json = file_get_contents('http://127.0.0.1:8001' . $path, false, stream_context_create(['http' => $http]));
        $headers = http_get_last_response_headers();

        return [
            'status' => (int) explode(' ', $headers[0])[1],
            'body' => json_decode($json, true),
        ];
    }

    protected function countUsers(): int
    {
        return (int) self::$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    protected function countSessions(): int
    {
        return (int) self::$pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    }
}
