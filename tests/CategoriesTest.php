<?php

namespace Tests;

class CategoriesTest extends ApiTestCase
{
    // Successful create: valid input creates the category for the logged-in user and returns 201

    public function test_create_category(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], 'Food');

        $this->assertSame(201, $response['status']);
        $this->assertSame([
            'success' => true,
            'category_id' => 1,
            'name' => 'Food',
        ], $response['body']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_create_category_trims_name(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], '  Food  ');

        $this->assertSame(201, $response['status']);
        $this->assertSame('Food', $response['body']['name']);
        $stored = self::$pdo->query('SELECT name FROM categories')->fetchColumn();
        $this->assertSame('Food', $stored);
    }

    public function test_create_category_with_numbers_in_name(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], 'Food 365');

        $this->assertSame(201, $response['status']);
        $this->assertSame('Food 365', $response['body']['name']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_create_category_name_100(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], str_repeat('a', 100));

        $this->assertSame(201, $response['status']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_create_category_name_100_multibyte(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], str_repeat('ä', 100));

        $this->assertSame(201, $response['status']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_create_category_belongs_to_logged_in_user(): void
    {
        $user = $this->registerUser();

        $this->createCategory($user['token'], 'Food');

        $stored = (int) self::$pdo->query('SELECT user_id FROM categories')->fetchColumn();
        $this->assertSame($user['user_id'], $stored);
    }

    public function test_create_category_ignores_user_id_in_body(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');

        $response = $this->post('/categories', [
            'name' => 'Food',
            'user_id' => $firstUser['user_id'],
        ], ['Authorization: Bearer ' . $secondUser['token']]);

        $this->assertSame(201, $response['status']);
        $stored = (int) self::$pdo->query('SELECT user_id FROM categories')->fetchColumn();
        $this->assertSame($secondUser['user_id'], $stored);
    }

    public function test_create_same_name_for_two_users(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');

        $firstResponse = $this->createCategory($firstUser['token'], 'Food');
        $secondResponse = $this->createCategory($secondUser['token'], 'Food');

        $this->assertSame(201, $firstResponse['status']);
        $this->assertSame(201, $secondResponse['status']);
        $this->assertSame(2, $this->countCategories());
    }

    public function test_create_two_different_names(): void
    {
        $user = $this->registerUser();

        $firstResponse = $this->createCategory($user['token'], 'Food');
        $secondResponse = $this->createCategory($user['token'], 'Food 365');

        $this->assertSame(201, $firstResponse['status']);
        $this->assertSame(201, $secondResponse['status']);
        $this->assertSame(2, $this->countCategories());
    }

    // Request body: Request::json() must return a JSON object, otherwise 400

    public function test_create_category_rejects_plain_text(): void
    {
        $user = $this->registerUser();

        $response = $this->postRaw('/categories', 'hello', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_empty_body(): void
    {
        $user = $this->registerUser();

        $response = $this->postRaw('/categories', '', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_valid_json_but_not_object(): void
    {
        $user = $this->registerUser();

        $response = $this->postRaw('/categories', '"hello"', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    // Name field, missing or not text: caught by isset and is_string

    public function test_create_category_rejects_missing_name_field(): void
    {
        $user = $this->registerUser();

        $response = $this->post('/categories', [], ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_name_as_null(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], null);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_name_as_number(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], 365);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name must be text'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_name_as_list(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], ['Food']);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name must be text'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    // Name field, empty or too long: checked after mb_trim, length counted with mb_strlen

    public function test_create_category_rejects_empty_name(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], '');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_whitespace_only_name(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], '   ');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_name_too_long(): void
    {
        $user = $this->registerUser();

        $response = $this->createCategory($user['token'], str_repeat('a', 101));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name must be at most 100 characters'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    // Duplicate name: the UNIQUE (user_id, name) key makes the insert fail, answered with 409

    public function test_create_category_rejects_duplicate_name(): void
    {
        $user = $this->registerUser();

        $firstResponse = $this->createCategory($user['token'], 'Food');

        $this->assertSame(201, $firstResponse['status']);

        $secondResponse = $this->createCategory($user['token'], 'Food');

        $this->assertSame(409, $secondResponse['status']);
        $this->assertSame(['error' => 'category already exists'], $secondResponse['body']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_create_category_rejects_duplicate_name_other_case(): void
    {
        $user = $this->registerUser();

        $firstResponse = $this->createCategory($user['token'], 'Food');

        $this->assertSame(201, $firstResponse['status']);

        $secondResponse = $this->createCategory($user['token'], 'food');

        $this->assertSame(409, $secondResponse['status']);
        $this->assertSame(['error' => 'category already exists'], $secondResponse['body']);
        $this->assertSame(1, $this->countCategories());
    }

    // Token check: requireUserId() in index.php answers 401 before create() runs

    public function test_create_category_rejects_missing_header(): void
    {
        $response = $this->post('/categories', ['name' => 'Food']);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_create_category_rejects_unknown_token(): void
    {
        $this->registerUser();
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->createCategory($unknownToken, 'Food');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    // Successful list: GET /categories returns only the logged-in user's categories, sorted by name

    public function test_list_categories_empty(): void
    {
        $user = $this->registerUser();

        $response = $this->listCategories($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['categories' => []], $response['body']);
    }

    public function test_list_categories(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->listCategories($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'categories' => [
                ['id' => 1, 'name' => 'Food'],
            ],
        ], $response['body']);
    }

    public function test_list_categories_sorted_by_name(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createCategory($user['token'], 'Bills');
        $this->createCategory($user['token'], 'Rent');

        $response = $this->listCategories($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'categories' => [
                ['id' => 2, 'name' => 'Bills'],
                ['id' => 1, 'name' => 'Food'],
                ['id' => 3, 'name' => 'Rent'],
            ],
        ], $response['body']);
    }

    public function test_list_categories_only_own(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Rent');

        $firstResponse = $this->listCategories($firstUser['token']);
        $secondResponse = $this->listCategories($secondUser['token']);

        $this->assertSame(['categories' => [['id' => 1, 'name' => 'Food']]], $firstResponse['body']);
        $this->assertSame(['categories' => [['id' => 2, 'name' => 'Rent']]], $secondResponse['body']);
    }

    public function test_list_categories_empty_for_other_user(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');

        $response = $this->listCategories($secondUser['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['categories' => []], $response['body']);
    }

    // Token check: requireUserId() in index.php answers 401 before list() runs

    public function test_list_categories_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->get('/categories');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    public function test_list_categories_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->listCategories($unknownToken);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    // Successful update: PUT /categories/{id} renames the logged-in user's category

    public function test_update_category(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, 'Groceries');

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'success' => true,
            'category_id' => 1,
            'name' => 'Groceries',
        ], $response['body']);
        $this->assertSame('Groceries', $this->categoryName(1));
    }

    public function test_update_category_trims_name(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, '  Groceries  ');

        $this->assertSame(200, $response['status']);
        $this->assertSame('Groceries', $response['body']['name']);
        $this->assertSame('Groceries', $this->categoryName(1));
    }

    public function test_update_category_same_name(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, 'Food');

        $this->assertSame(200, $response['status']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_change_case(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, 'food');

        $this->assertSame(200, $response['status']);
        $this->assertSame('food', $this->categoryName(1));
    }

    // Request body and name: the same readName() checks as create, answered with 400

    public function test_update_category_rejects_plain_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->putRaw('/categories/1', 'hello', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_rejects_whitespace_only_name(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, '   ');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_rejects_name_too_long(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 1, str_repeat('a', 101));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name must be at most 100 characters'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    // Duplicate name: renaming to the name of another own category fails on the UNIQUE key, answered with 409

    public function test_update_category_rejects_duplicate_name(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createCategory($user['token'], 'Bills');

        $response = $this->updateCategory($user['token'], 2, 'Food');

        $this->assertSame(409, $response['status']);
        $this->assertSame(['error' => 'category already exists'], $response['body']);
        $this->assertSame('Bills', $this->categoryName(2));
    }

    public function test_update_category_to_name_of_other_user(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Bills');

        $response = $this->updateCategory($secondUser['token'], 2, 'Food');

        $this->assertSame(200, $response['status']);
        $this->assertSame('Food', $this->categoryName(2));
    }

    // Not found: WHERE id AND user_id finds no row, answered with 404

    public function test_update_category_rejects_unknown_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->updateCategory($user['token'], 999, 'Groceries');

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_rejects_other_users_category(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');

        $response = $this->updateCategory($secondUser['token'], 1, 'Hacked');

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_rejects_non_numeric_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->put('/categories/abc', ['name' => 'Groceries'], ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'Not found'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    // Token check: requireUserId() in index.php answers 401 before update() runs

    public function test_update_category_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->put('/categories/1', ['name' => 'Groceries']);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_update_category_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->updateCategory($unknownToken, 1, 'Groceries');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    // Successful delete: DELETE /categories/{id} removes the logged-in user's category

    public function test_delete_category(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->deleteCategory($user['token'], 1);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['success' => true], $response['body']);
        $this->assertSame(0, $this->countCategories());
    }

    public function test_delete_category_only_deletes_this_category(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createCategory($user['token'], 'Bills');

        $response = $this->deleteCategory($user['token'], 1);

        $this->assertSame(200, $response['status']);
        $this->assertSame(1, $this->countCategories());
        $this->assertFalse($this->categoryName(1));
        $this->assertSame('Bills', $this->categoryName(2));
    }

    public function test_delete_category_name_can_be_used_again(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->deleteCategory($user['token'], 1);

        $response = $this->createCategory($user['token'], 'Food');

        $this->assertSame(201, $response['status']);
        $this->assertSame(2, $response['body']['category_id']);
        $this->assertSame(1, $this->countCategories());
    }

    // In use: ON DELETE RESTRICT on transactions makes the delete fail, answered with 409

    public function test_delete_category_rejects_category_with_transactions(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->insertTransaction($user['user_id'], 1);

        $response = $this->deleteCategory($user['token'], 1);

        $this->assertSame(409, $response['status']);
        $this->assertSame(['error' => 'category is used by transactions'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    // Not found: WHERE id AND user_id finds no row, answered with 404

    public function test_delete_category_rejects_unknown_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->deleteCategory($user['token'], 999);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_delete_category_rejects_same_id_twice(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $firstResponse = $this->deleteCategory($user['token'], 1);

        $this->assertSame(200, $firstResponse['status']);

        $secondResponse = $this->deleteCategory($user['token'], 1);

        $this->assertSame(404, $secondResponse['status']);
        $this->assertSame(['error' => 'category not found'], $secondResponse['body']);
    }

    public function test_delete_category_rejects_other_users_category(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');

        $response = $this->deleteCategory($secondUser['token'], 1);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame('Food', $this->categoryName(1));
    }

    public function test_delete_category_rejects_non_numeric_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->delete('/categories/abc', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'Not found'], $response['body']);
        $this->assertSame(1, $this->countCategories());
    }

    // Token check: requireUserId() in index.php answers 401 before delete() runs

    public function test_delete_category_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->delete('/categories/1');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countCategories());
    }

    public function test_delete_category_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->deleteCategory($unknownToken, 1);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countCategories());
    }

    // Helpers: create the user through the real register endpoint, send the token as a Bearer header

    private function registerUser(string $email = 'test@example.com'): array
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => $email,
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $response['status']);

        return $response['body'];
    }

    private function createCategory(string $token, mixed $name): array
    {
        return $this->post('/categories', ['name' => $name], ['Authorization: Bearer ' . $token]);
    }

    private function listCategories(string $token): array
    {
        return $this->get('/categories', ['Authorization: Bearer ' . $token]);
    }

    private function updateCategory(string $token, int $id, mixed $name): array
    {
        return $this->put('/categories/' . $id, ['name' => $name], ['Authorization: Bearer ' . $token]);
    }

    private function deleteCategory(string $token, int $id): array
    {
        return $this->delete('/categories/' . $id, ['Authorization: Bearer ' . $token]);
    }

    private function insertTransaction(int $userId, int $categoryId): void
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO transactions (user_id, category_id, amount, currency, date, payment_method)
            VALUES (:user_id, :category_id, 12.50, :currency, CURDATE(), :payment_method)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'currency' => 'EUR',
            'payment_method' => 'card',
        ]);
    }

    private function categoryName(int $id): string|false
    {
        $stmt = self::$pdo->prepare('SELECT name FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn();
    }

    private function countCategories(): int
    {
        return (int) self::$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    }
}
