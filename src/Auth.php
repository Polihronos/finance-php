<?php

namespace App;

use PDO;
use PDOException;

class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function register(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // check if empty
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'name, email and password are required']);
            return;
        }
        // check if valid
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid email']);
            return;
        }
        // check if password > 8
        if (strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'password must be at least 8 characters']);
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );

        try {
            $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'hash' => $hash,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['error' => 'email already registered']);
                return;
            }
            throw $e;
        }

        http_response_code(201);

        echo json_encode ([
            'success' => true,
            'user_id' => (int) $this->pdo->lastInsertId(),
        ]);
    }
}
