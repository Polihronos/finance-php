<?php

namespace App;

use PDO;
use PDOException;

class Categories
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId): void
    {
        $name = $this->readName();
        if ($name === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (user_id, name) VALUES (:user_id, :name)'
        );

        try {
            $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            ]);

            $categoryId = (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['error' => 'category already exists']);
                return;
            }
            throw $e;
        }
        http_response_code(201);
        echo json_encode ([
            'success' => true,
            'category_id' => $categoryId,
            'name' => $name,
        ]);
    }

    public function list(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name FROM categories WHERE user_id = :user_id ORDER BY name'
        );

        $stmt->execute([
        'user_id' => $userId,
        ]);

        $rows = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode(['categories' => $rows]);
    }

    public function update(int $userId, int $id): void
    {
        $name = $this->readName();
        if ($name === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE categories SET name = :name WHERE id = :id AND user_id = :user_id'
        );

        try {
            $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'id' => $id,
            ]);

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['error' => 'category already exists']);
                return;
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'category not found']);
            return;
        }

        http_response_code(200);
        echo json_encode ([
            'success' => true,
            'category_id' => $id,
            'name' => $name,
        ]);
    }

    public function delete(int $userId, int $id): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM categories WHERE id = :id AND user_id = :user_id'
        );

        try {
            $stmt->execute([
            'user_id' => $userId,
            'id' => $id,
            ]);

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['error' => 'category is used by transactions']);
                return;
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'category not found' ]);
            return;
        }

        http_response_code(200);
        echo json_encode ([
            'success' => true,
        ]);

    }

    private function readName(): ?string
    {
        $data = Request::json();

        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'request body must be JSON']);
            return null;
        }

        if (!isset($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => "name is required"]);
            return null;
        }

        if (!is_string($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => "name must be text"]);
            return null;
        }

        $data['name'] = mb_trim($data['name']);
        if ($data['name'] === '') {
            http_response_code(400);
            echo json_encode(['error' => "name is required"]);
            return null;
        }

        if (mb_strlen($data['name']) > 100) {
            http_response_code(400);
            echo json_encode(['error' => "name must be at most 100 characters"]);
            return null;
        }

        return $data['name'];
    }
}
