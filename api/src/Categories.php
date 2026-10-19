<?php

namespace App;

use DateTimeImmutable;
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
                throw new HttpException(409, 'category already exists');
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
                throw new HttpException(409, 'category already exists');
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'category not found');
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
                throw new HttpException(409, 'category is used by transactions');
            }
            throw $e;
        }

        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'category not found');
        }

        http_response_code(200);
        echo json_encode ([
            'success' => true,
        ]);

    }

    private function readName(): string
    {
        $data = Request::json();

        if ($data === null) {
            throw new HttpException(400, 'request body must be JSON');
        }

        if (!isset($data['name'])) {
            throw new HttpException(400, "name is required");
        }

        if (!is_string($data['name'])) {
            throw new HttpException(400, "name must be text");
        }

        $data['name'] = mb_trim($data['name']);
        if ($data['name'] === '') {
            throw new HttpException(400, "name is required");
        }

        if (mb_strlen($data['name']) > 100) {
            throw new HttpException(400, "name must be at most 100 characters");
        }

        return $data['name'];
    }

    private function readPeriod(): array
    {
        $month = $_GET['month'] ?? null;
        $year = $_GET['year'] ?? null;

        if ($month !== null && $year !== null) {
            throw new HttpException(400, 'send month or year, not both');
        }

        if ($month !== null) {
            if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new HttpException(400, 'month must be in YYYY-MM format');
            }

            $start = new DateTimeImmutable("$month-01");

            return [$start->format('Y-m-d'), $start->modify('+1 month')->format('Y-m-d')];
        }

        if ($year !== null) {
            if (!is_string($year) || !preg_match('/^\d{4}$/', $year)) {
                throw new HttpException(400, 'year must be in YYYY format');
            }

            $start = new DateTimeImmutable("$year-01-01");

            return [$start->format('Y-m-d'), $start->modify('+1 year')->format('Y-m-d')];
        }

        throw new HttpException(400, 'month or year is required');
    }

    public function stats(int $userId): void
    {
        [$start, $end] = $this->readPeriod();

        $stmt = $this->pdo->prepare(
            'SELECT
                c.id AS category_id,
                COUNT(t.id) AS transaction_count,
                COALESCE(SUM(t.amount), 0) AS total
            FROM categories c
            LEFT JOIN transactions t
                ON t.category_id = c.id AND t.date >= :start AND t.date < :end
            WHERE c.user_id = :user_id
            GROUP BY c.id'
        );

        $stmt->execute([
            'user_id' => $userId,
            'start' => $start,
            'end' => $end,
        ]);

        $rows = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode(['stats' => $rows]);
    }
}
