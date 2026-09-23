<?php

namespace App;

use PDO;

class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function register(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );

        $stmt->execute([
           'name' => $data['name'],
           'email' => $data['email'],
           'hash' => $hash,
        ]);

        echo json_encode ([
            'success' => true,
            'user_id' => $this->pdo->lastInsertId(),
        ]);
    }
}
