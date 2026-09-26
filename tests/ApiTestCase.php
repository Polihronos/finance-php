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
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function post(string $path, array $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($body),
                'ignore_errors' => true,
            ],
        ]);

        $json = file_get_contents('http://127.0.0.1:8001' . $path, false, $context);
        $headers = http_get_last_response_headers();

        return [
            'status' => (int) explode(' ', $headers[0])[1],
            'body' => json_decode($json, true),
        ];
    }
}
