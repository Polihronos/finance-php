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
            if ($e->getcode() === '23000') {
                http_response_code('409');
                echo json_encode(['error' => 'email already registered']);
                return;
            }
        throw $e;
        }

        echo json_encode ([
            'success' => true,
            'user_id' => $this->pdo->lastInsertId(),
        ]);
    }
}
