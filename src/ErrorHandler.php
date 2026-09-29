<?php

namespace App;

use ErrorException;
use Throwable;

class ErrorHandler
{
    public static function register(): void
    {
        // stop the html error pages
        ini_set('display_errors', '0');

        set_error_handler(function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            error_log((string) $e);
            http_response_code(500);
            header('Content-type: application/json');
            echo json_encode(['error' => 'Internal server error']);
        });
    }
}
