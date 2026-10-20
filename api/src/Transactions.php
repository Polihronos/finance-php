<?php

namespace App;

use PDO;

class Transactions
{
    private const COLUMNS = 'id, category_id, amount, currency, date, payment_method, description, place';
    private const CURRENCIES = ['EUR'];
    private const PAYMENT_METHODS = ['cash', 'card'];

    public function __construct(private PDO $pdo)
    {
    }

    private function readText(array $data, string $field, int $max): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }

        if (!is_string($data[$field])) {
            throw new HttpException(400, "$field must be text");
        }

        $text = mb_trim($data[$field]);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw new HttpException(400, "$field must be at most $max characters");
        }

        return $text;
    }

    private function readAmount(array $data): string
    {
        if (!isset($data['amount'])) {
            throw new HttpException(400, 'amount is required');
        }

        $amount = $data['amount'];

        if (!is_int($amount) && !is_float($amount)) {
            throw new HttpException(400, 'amount must be a number');
        }

        if ($amount <= 0) {
            throw new HttpException(400, 'amount must be more than 0');
        }

        if ($amount > 9999999999.99) {
            throw new HttpException(400, 'amount must be at most 9999999999.99');
        }

        $text = (string) $amount;

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
            throw new HttpException(400, 'amount must have at most 2 decimals');
        }

        return $text;
    }

    private function readDate(array $data, string $field = 'date'): string
    {
        $date = $this->readText($data, $field, 10)
            ?? throw new HttpException(400, "$field is required");

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            throw new HttpException(400, "$field must be in YYYY-MM-DD format");
        }

        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new HttpException(400, "$field is not a real date");
        }

        return $date;
    }

    private function readRange(): ?array
    {
        if (!isset($_GET['from']) && !isset($_GET['to'])) {
            return null;
        }

        $from = $this->readDate($_GET, 'from');
        $to = $this->readDate($_GET, 'to');

        if ($from >= $to) {
            throw new HttpException(400, 'from must be before to');
        }

        return [$from, $to];
    }

    private function readCategoryId(array $data, int $userId): int
    {
        if (!isset($data['category_id'])) {
            throw new HttpException(400, 'category_id is required');
        }

        if (!is_int($data['category_id'])) {
            throw new HttpException(400, 'category_id must be a whole number');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM categories WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $data['category_id'],
            'user_id' => $userId,
        ]);

        if ($stmt->fetch() === false) {
            throw new HttpException(400, 'category not found');
        }

        return $data['category_id'];
    }

    private function readCurrency(array $data): string
    {
        $currency = mb_strtoupper($this->readText($data, 'currency', 3) ?? 'EUR');

        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new HttpException(400, 'currency must be one of: ' . implode(', ', self::CURRENCIES));
        }

        return $currency;
    }

    private function readPaymentMethod(array $data): string
    {
        $method = $this->readText($data, 'payment_method', 50)
            ?? throw new HttpException(400, 'payment_method is required');

        $method = mb_strtolower($method);

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new HttpException(400, 'payment_method must be one of: ' . implode(', ', self::PAYMENT_METHODS));
        }

        return $method;
    }

    private function readTransaction(int $userId): array
    {
        $data = Request::json()
            ?? throw new HttpException(400, 'request body must be JSON');

        return [
            'category_id' => $this->readCategoryId($data, $userId),
            'amount' => $this->readAmount($data),
            'currency' => $this->readCurrency($data),
            'date' => $this->readDate($data),
            'payment_method' => $this->readPaymentMethod($data),
            'description' => $this->readText($data, 'description', 255),
            'place' => $this->readText($data, 'place', 255),
        ];
    }

    private function find(int $userId, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM transactions WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $stmt->fetch();
    }

    public function create(int $userId): void
    {
        $transaction = $this->readTransaction($userId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions (user_id, category_id, amount, currency, date, payment_method, description, place)
            VALUES (:user_id, :category_id, :amount, :currency, :date, :payment_method, :description, :place)'
        );
        $stmt->execute(['user_id' => $userId] + $transaction);

        $id = (int) $this->pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'transaction' => $this->find($userId, $id),
        ]);
    }

    public function list(int $userId): void
    {
        $range = $this->readRange();

        if ($range === null) {
            $stmt = $this->pdo->prepare(
                'SELECT ' . self::COLUMNS . ' FROM transactions WHERE user_id = :user_id ORDER BY date DESC, id DESC'
            );
            $stmt->execute([
                'user_id' => $userId,
            ]);

            http_response_code(200);
            echo json_encode(['transactions' => $stmt->fetchAll(), 'has_older' => false]);
            return;
        }

        [$from, $to] = $range;

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM transactions
            WHERE user_id = :user_id AND date >= :from AND date < :to
            ORDER BY date DESC, id DESC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
        ]);
        $transactions = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT EXISTS (SELECT 1 FROM transactions WHERE user_id = :user_id AND date < :from)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'from' => $from,
        ]);

        http_response_code(200);
        echo json_encode(['transactions' => $transactions, 'has_older' => (bool) $stmt->fetchColumn()]);
    }

    public function update(int $userId, int $id): void
    {
        $transaction = $this->readTransaction($userId);

        $stmt = $this->pdo->prepare(
            'UPDATE transactions
            SET category_id = :category_id, amount = :amount, currency = :currency, date = :date,
                payment_method = :payment_method, description = :description, place = :place
            WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId] + $transaction);

        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'transaction not found');
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'transaction' => $this->find($userId, $id),
        ]);
    }

    public function delete(int $userId, int $id): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM transactions WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new HttpException(404, 'transaction not found');
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    }

    public function suggestions(int $userId): void
    {
        $query = $this->readText($_GET, 'q', 255)
            ?? throw new HttpException(400, 'q is required');

        $stmt = $this->pdo->prepare(
            'SELECT description, category_id, place
            FROM (
                SELECT description, category_id, place,
                    ROW_NUMBER() OVER (PARTITION BY description ORDER BY date DESC, id DESC) AS row_num,
                    COUNT(*) OVER (PARTITION BY description) AS uses
                FROM transactions
                WHERE user_id = :user_id AND description LIKE :prefix
            ) AS matches
            WHERE row_num = 1
            ORDER BY uses DESC, description
            LIMIT 8'
        );
        $stmt->execute([
            'user_id' => $userId,
            'prefix' => addcslashes($query, '%_\\') . '%',
        ]);

        http_response_code(200);
        echo json_encode(['suggestions' => $stmt->fetchAll()]);
    }
}
