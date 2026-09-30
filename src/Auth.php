<?php

namespace App;

use PDO;
use PDOException;
use Throwable;

class Auth
{
    private const DUMMY_HASH = '$2y$12$gKMX8cpafGzHjhru57/rGeTb0pscojMgA7kWYs3Y5gnUqsVEU8.yC';

    public function __construct(private PDO $pdo)
    {
    }

    public function register(): void
    {
        $data = Request::json();

        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'request body must be JSON']);
            return;
        }

        foreach (['name', 'email', 'password'] as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "$field is required"]);
                return;
            }

            if (!is_string($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "$field must be text"]);
                return;
            }
        }

        $data['name'] = mb_trim($data['name']);

        foreach (['name', 'email', 'password'] as $field) {
            if ($data[$field] === '') {
                http_response_code(400);
                echo json_encode(['error' => "$field is required"]);
                return;
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid email']);
            return;
        }

        if (mb_strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'password must be at least 8 characters']);
            return;
        }

        if (mb_strlen($data['password']) > 36) {
            http_response_code(400);
            echo json_encode(['error' => 'password must be less than 36 characters']);
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );

        $this->pdo->beginTransaction();

        try {
            $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'hash' => $hash,
            ]);

            $userId = (int) $this->pdo->lastInsertId();
            $token = $this->createSession($userId);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['error' => 'email already registered']);
                return;
            }
            throw $e;
        }

        http_response_code(201);
        echo json_encode ([
            'success' => true,
            'user_id' => $userId,
            'token' => $token,
        ]);
    }

    public function login(): void
    {
        $data = Request::json();

        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'request body must be JSON']);
            return;
        }

        foreach (['email', 'password'] as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "$field is required"]);
                return;
            }

            if (!is_string($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "$field must be text"]);
                return;
            }
        }
        // look up the user by email
        $stmt = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => $data['email']]);
        $user = $stmt->fetch();

        // always run password_verify once, even for an unknown email,
        // so an attacker can't tell registered emails apart by response time
        $hash = $user === false ? self::DUMMY_HASH : $user['password_hash'];
        $passwordCorrect = password_verify($data['password'], $hash);

        if ($user === false || !$passwordCorrect) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid email or password']);
            return;
        }

        $token = $this->createSession($user['id']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'token' => $token,
        ]);
    }

    private function createSession(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY))'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }
}
