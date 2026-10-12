<?php

namespace App;

use PDO;
use PDOException;
use Throwable;

class Auth
{
    private const DUMMY_HASH = '$2y$12$gKMX8cpafGzHjhru57/rGeTb0pscojMgA7kWYs3Y5gnUqsVEU8.yC';
    private ?string $tokenHash = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function register(): void
    {
        $data = Request::json();

        if ($data === null) {
            throw new HttpException(400, 'request body must be JSON');
        }

        foreach (['name', 'email', 'password'] as $field) {
            if (!isset($data[$field])) {
                throw new HttpException(400, "$field is required");
            }

            if (!is_string($data[$field])) {
                throw new HttpException(400, "$field must be text");
            }
        }

        $data['name'] = mb_trim($data['name']);

        foreach (['name', 'email', 'password'] as $field) {
            if ($data[$field] === '') {
                throw new HttpException(400, "$field is required");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, 'invalid email');
        }

        if (mb_strlen($data['password']) < 8) {
            throw new HttpException(400, 'password must be at least 8 characters');
        }

        if (mb_strlen($data['password']) > 36) {
            throw new HttpException(400, 'password must be less than 36 characters');
        }

        // .env.testing sets BCRYPT_COST=4 because hashing at cost 12 was most of the test time.
        // Without it, real users get cost 12, the same as DUMMY_HASH, so login timing stays equal.
        $hash = password_hash($data['password'], PASSWORD_DEFAULT, [
            'cost' => (int) ($_ENV['BCRYPT_COST'] ?? 12),
        ]);

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
                throw new HttpException(409, 'email already registered');
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
            throw new HttpException(400, 'request body must be JSON');
        }

        foreach (['email', 'password'] as $field) {
            if (!isset($data[$field])) {
                throw new HttpException(400, "$field is required");
            }

            if (!is_string($data[$field])) {
                throw new HttpException(400, "$field must be text");
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
            throw new HttpException(401, 'invalid email or password');
        }

        $token = $this->createSession($user['id']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'token' => $token,
        ]);
    }

    public function requireUserId(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer ([0-9a-f]{64})$/', $header, $matches)) {
            throw new HttpException(401, 'not logged in');
        }

        $stmt = $this->pdo->prepare(
            'SELECT user_id, expires_at > NOW() AS active FROM sessions WHERE token_hash = :token_hash'
        );
        $this->tokenHash = hash('sha256', $matches[1]);
        $stmt->execute(['token_hash' => $this->tokenHash]);
        $session = $stmt->fetch();

        if ($session === false) {
            throw new HttpException(401, 'not logged in');
        }

        if (!$session['active']) {
            $this->pdo->exec('DELETE FROM sessions WHERE expires_at <= NOW()');
            throw new HttpException(401, 'not logged in');
        }

        return $session['user_id'];
    }

    public function logout(): void
    {
        $this->requireUserId();

        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $this->tokenHash]);

        http_response_code(200);
        echo json_encode(['success' => true]);
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
