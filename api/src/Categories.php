<?php

namespace App;

use DateTimeImmutable;
use PDO;
use PDOException;

class Categories
{
    private const ICONS = [
        'shopping-cart', 'utensils', 'coffee', 'car', 'fuel', 'bus', 'plane', 'house', 'zap',
        'droplet', 'wifi', 'smartphone', 'heart-pulse', 'pill', 'shirt', 'gift', 'film',
        'gamepad-2', 'dumbbell', 'graduation-cap', 'baby', 'paw-print', 'briefcase', 'piggy-bank',
        'tag',
    ];
    private const COLORS = ['red', 'orange', 'yellow', 'green', 'blue', 'purple'];
    private const DEFAULT_ICON = 'tag';
    private const DEFAULT_COLOR = 'blue';

    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId): void
    {
        $category = $this->readCategory();
        $icon = $category['icon'] ?? self::DEFAULT_ICON;
        $color = $category['color'] ?? self::DEFAULT_COLOR;

        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (user_id, name, icon, color) VALUES (:user_id, :name, :icon, :color)'
        );

        try {
            $stmt->execute([
            'user_id' => $userId,
            'name' => $category['name'],
            'icon' => $icon,
            'color' => $color,
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
            'name' => $category['name'],
            'icon' => $icon,
            'color' => $color,
        ]);
    }

    public function list(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, icon, color FROM categories WHERE user_id = :user_id ORDER BY name'
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
        $category = $this->readCategory();

        $stmt = $this->pdo->prepare(
            'UPDATE categories
            SET name = :name, icon = COALESCE(:icon, icon), color = COALESCE(:color, color)
            WHERE id = :id AND user_id = :user_id'
        );

        try {
            $stmt->execute([
            'user_id' => $userId,
            'name' => $category['name'],
            'icon' => $category['icon'],
            'color' => $category['color'],
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

        $stmt = $this->pdo->prepare('SELECT name, icon, color FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $saved = $stmt->fetch();

        http_response_code(200);
        echo json_encode ([
            'success' => true,
            'category_id' => $id,
            'name' => $saved['name'],
            'icon' => $saved['icon'],
            'color' => $saved['color'],
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

    private function readCategory(): array
    {
        $data = Request::json();

        if ($data === null) {
            throw new HttpException(400, 'request body must be JSON');
        }

        return [
            'name' => $this->readName($data),
            'icon' => $this->readChoice($data, 'icon', self::ICONS),
            'color' => $this->readChoice($data, 'color', self::COLORS),
        ];
    }

    private function readChoice(array $data, string $field, array $allowed): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }

        if (!is_string($data[$field])) {
            throw new HttpException(400, "$field must be text");
        }

        if (!in_array($data[$field], $allowed, true)) {
            throw new HttpException(400, "$field must be one of: " . implode(', ', $allowed));
        }

        return $data[$field];
    }

    private function readName(array $data): string
    {
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
