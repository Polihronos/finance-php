<?php

namespace Tests;

class TransactionsTest extends ApiTestCase
{
    // Successful create: valid input saves the transaction for the logged-in user and returns 201 with the saved row

    public function test_create_transaction(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody());

        $this->assertSame(201, $response['status']);
        $this->assertSame([
            'success' => true,
            'transaction' => [
                'id' => 1,
                'category_id' => 1,
                'amount' => '12.50',
                'currency' => 'EUR',
                'date' => '2026-10-12',
                'payment_method' => 'card',
                'description' => 'Lunch',
                'place' => 'Office',
            ],
        ], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    public function test_create_transaction_belongs_to_logged_in_user(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $this->createTransaction($user['token'], $this->transactionBody());

        $this->assertSame($user['user_id'], $this->storedTransaction(1)['user_id']);
    }

    public function test_create_transaction_ignores_user_id_in_body(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($secondUser['token'], 'Food');

        $response = $this->createTransaction($secondUser['token'], $this->transactionBody(1, [
            'user_id' => $firstUser['user_id'],
        ]));

        $this->assertSame(201, $response['status']);
        $this->assertSame($secondUser['user_id'], $this->storedTransaction(1)['user_id']);
    }

    public function test_create_transaction_without_optional_fields(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], [
            'category_id' => 1,
            'amount' => 12.5,
            'date' => '2026-10-12',
            'payment_method' => 'card',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame('EUR', $response['body']['transaction']['currency']);
        $this->assertNull($response['body']['transaction']['description']);
        $this->assertNull($response['body']['transaction']['place']);
        $this->assertNull($this->storedTransaction(1)['description']);
        $this->assertNull($this->storedTransaction(1)['place']);
    }

    public function test_create_transaction_trims_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, [
            'description' => '  Lunch  ',
            'place' => '  Office  ',
        ]));

        $this->assertSame(201, $response['status']);
        $this->assertSame('Lunch', $this->storedTransaction(1)['description']);
        $this->assertSame('Office', $this->storedTransaction(1)['place']);
    }

    public function test_create_transaction_whitespace_only_text_saved_as_null(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, [
            'description' => '   ',
            'place' => '',
        ]));

        $this->assertSame(201, $response['status']);
        $this->assertNull($this->storedTransaction(1)['description']);
        $this->assertNull($this->storedTransaction(1)['place']);
    }

    public function test_create_transaction_uppercases_currency(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['currency' => 'eur']));

        $this->assertSame(201, $response['status']);
        $this->assertSame('EUR', $this->storedTransaction(1)['currency']);
    }

    public function test_create_transaction_lowercases_payment_method(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['payment_method' => 'Card']));

        $this->assertSame(201, $response['status']);
        $this->assertSame('card', $this->storedTransaction(1)['payment_method']);
    }

    public function test_create_transaction_with_cash(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['payment_method' => 'cash']));

        $this->assertSame(201, $response['status']);
        $this->assertSame('cash', $this->storedTransaction(1)['payment_method']);
    }

    public function test_create_transaction_whole_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 12]));

        $this->assertSame(201, $response['status']);
        $this->assertSame('12.00', $this->storedTransaction(1)['amount']);
    }

    public function test_create_transaction_smallest_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 0.01]));

        $this->assertSame(201, $response['status']);
        $this->assertSame('0.01', $this->storedTransaction(1)['amount']);
    }

    public function test_create_transaction_largest_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 9999999999.99]));

        $this->assertSame(201, $response['status']);
        $this->assertSame('9999999999.99', $this->storedTransaction(1)['amount']);
    }

    public function test_create_transaction_leap_day(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2028-02-29']));

        $this->assertSame(201, $response['status']);
        $this->assertSame('2028-02-29', $this->storedTransaction(1)['date']);
    }

    public function test_create_transaction_description_255_multibyte(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, [
            'description' => str_repeat('ä', 255),
        ]));

        $this->assertSame(201, $response['status']);
        $this->assertSame(str_repeat('ä', 255), $this->storedTransaction(1)['description']);
    }

    // Request body: Request::json() must return a JSON object, otherwise 400

    public function test_create_transaction_rejects_plain_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->postRaw('/transactions', 'hello', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_empty_body(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->postRaw('/transactions', '', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_valid_json_but_not_object(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->postRaw('/transactions', '"hello"', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Category: readCategoryId() needs a whole number that is one of the user's own categories

    public function test_create_transaction_rejects_missing_category_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $body = $this->transactionBody();
        unset($body['category_id']);

        $response = $this->createTransaction($user['token'], $body);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category_id is required'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_category_id_as_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['category_id' => '1']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category_id must be a whole number'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_category_id_with_decimals(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['category_id' => 1.5]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category_id must be a whole number'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_unknown_category(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(999));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_other_users_category(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');

        $response = $this->createTransaction($secondUser['token'], $this->transactionBody(1));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Amount: readAmount() needs a number above 0 with at most 2 decimals that fits DECIMAL(12,2)

    public function test_create_transaction_rejects_missing_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $body = $this->transactionBody();
        unset($body['amount']);

        $response = $this->createTransaction($user['token'], $body);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount is required'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_amount_as_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => '12.50']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be a number'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_amount_as_true(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => true]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be a number'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_zero_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 0]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be more than 0'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_negative_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => -12.5]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be more than 0'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_three_decimals(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 12.345]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must have at most 2 decimals'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_amount_too_large(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['amount' => 10000000000]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be at most 9999999999.99'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Currency: readCurrency() defaults to EUR and only accepts values from CURRENCIES

    public function test_create_transaction_rejects_other_currency(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['currency' => 'USD']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'currency must be one of: EUR'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_currency_too_long(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['currency' => 'EURO']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'currency must be at most 3 characters'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_currency_as_number(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['currency' => 978]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'currency must be text'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Date: readDate() needs text in YYYY-MM-DD that is a real date

    public function test_create_transaction_rejects_missing_date(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $body = $this->transactionBody();
        unset($body['date']);

        $response = $this->createTransaction($user['token'], $body);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'date is required'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_date_as_number(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => 20261012]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'date must be text'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_wrong_date_format(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '12.10.2026']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'date must be in YYYY-MM-DD format'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_day_that_does_not_exist(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-02-30']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'date is not a real date'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_month_13(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-13-01']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'date is not a real date'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Payment method: readPaymentMethod() only accepts values from PAYMENT_METHODS

    public function test_create_transaction_rejects_missing_payment_method(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $body = $this->transactionBody();
        unset($body['payment_method']);

        $response = $this->createTransaction($user['token'], $body);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'payment_method is required'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_whitespace_only_payment_method(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['payment_method' => '   ']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'payment_method is required'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_unknown_payment_method(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['payment_method' => 'bank']));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'payment_method must be one of: cash, card'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Description and place: optional text checked by readText(), at most 255 characters

    public function test_create_transaction_rejects_description_too_long(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, [
            'description' => str_repeat('a', 256),
        ]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'description must be at most 255 characters'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_place_too_long(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, [
            'place' => str_repeat('a', 256),
        ]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'place must be at most 255 characters'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_place_as_list(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->createTransaction($user['token'], $this->transactionBody(1, ['place' => ['Office']]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'place must be text'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Token check: requireUserId() in index.php answers 401 before create() runs

    public function test_create_transaction_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        $response = $this->post('/transactions', $this->transactionBody());

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_create_transaction_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->createTransaction($unknownToken, $this->transactionBody());

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    // Successful list: GET /transactions returns only the logged-in user's transactions, newest date first

    public function test_list_transactions_empty(): void
    {
        $user = $this->registerUser();

        $response = $this->listTransactions($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['transactions' => [], 'has_older' => false], $response['body']);
    }

    public function test_list_transactions(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->listTransactions($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'transactions' => [
                [
                    'id' => 1,
                    'category_id' => 1,
                    'amount' => '12.50',
                    'currency' => 'EUR',
                    'date' => '2026-10-12',
                    'payment_method' => 'card',
                    'description' => 'Lunch',
                    'place' => 'Office',
                ],
            ],
            'has_older' => false,
        ], $response['body']);
    }

    public function test_list_transactions_newest_first(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-10-01']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-10-12']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-10-12']));

        $response = $this->listTransactions($user['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame([3, 2, 1], array_column($response['body']['transactions'], 'id'));
    }

    public function test_list_transactions_only_own(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Rent');
        $this->createTransaction($firstUser['token'], $this->transactionBody(1));
        $this->createTransaction($secondUser['token'], $this->transactionBody(2));

        $firstResponse = $this->listTransactions($firstUser['token']);
        $secondResponse = $this->listTransactions($secondUser['token']);

        $this->assertSame([1], array_column($firstResponse['body']['transactions'], 'id'));
        $this->assertSame([2], array_column($secondResponse['body']['transactions'], 'id'));
    }

    public function test_list_transactions_empty_for_other_user(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createTransaction($firstUser['token'], $this->transactionBody());

        $response = $this->listTransactions($secondUser['token']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['transactions' => [], 'has_older' => false], $response['body']);
    }

    // Date range: ?from= and ?to= return the transactions from the first day up to the day before to, and say whether older ones exist

    public function test_list_transactions_between_dates(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-07-31']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-08-01']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-10-31']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-11-01']));

        $response = $this->listTransactionsBetween($user['token'], '2026-08-01', '2026-11-01');

        $this->assertSame(200, $response['status']);
        $this->assertSame([3, 2], array_column($response['body']['transactions'], 'id'));
        $this->assertTrue($response['body']['has_older']);
    }

    public function test_list_transactions_between_dates_without_older(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-10-12']));

        $response = $this->listTransactionsBetween($user['token'], '2026-08-01', '2026-11-01');

        $this->assertSame(200, $response['status']);
        $this->assertSame([1], array_column($response['body']['transactions'], 'id'));
        $this->assertFalse($response['body']['has_older']);
    }

    public function test_list_transactions_between_dates_empty_with_older(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['date' => '2026-01-12']));

        $response = $this->listTransactionsBetween($user['token'], '2026-08-01', '2026-11-01');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['transactions' => [], 'has_older' => true], $response['body']);
    }

    public function test_list_transactions_between_dates_only_own(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Rent');
        $this->createTransaction($firstUser['token'], $this->transactionBody(1, ['date' => '2026-10-12']));
        $this->createTransaction($secondUser['token'], $this->transactionBody(2, ['date' => '2026-10-12']));
        $this->createTransaction($secondUser['token'], $this->transactionBody(2, ['date' => '2026-01-12']));

        $response = $this->listTransactionsBetween($firstUser['token'], '2026-08-01', '2026-11-01');

        $this->assertSame(200, $response['status']);
        $this->assertSame([1], array_column($response['body']['transactions'], 'id'));
        $this->assertFalse($response['body']['has_older']);
    }

    public function test_list_transactions_rejects_from_without_to(): void
    {
        $user = $this->registerUser();

        $response = $this->get('/transactions?from=2026-08-01', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'to is required'], $response['body']);
    }

    public function test_list_transactions_rejects_to_without_from(): void
    {
        $user = $this->registerUser();

        $response = $this->get('/transactions?to=2026-11-01', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'from is required'], $response['body']);
    }

    public function test_list_transactions_rejects_wrong_from_format(): void
    {
        $user = $this->registerUser();

        $response = $this->listTransactionsBetween($user['token'], '2026-08', '2026-11-01');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'from must be in YYYY-MM-DD format'], $response['body']);
    }

    public function test_list_transactions_rejects_to_that_does_not_exist(): void
    {
        $user = $this->registerUser();

        $response = $this->listTransactionsBetween($user['token'], '2026-08-01', '2026-02-30');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'to is not a real date'], $response['body']);
    }

    public function test_list_transactions_rejects_from_after_to(): void
    {
        $user = $this->registerUser();

        $response = $this->listTransactionsBetween($user['token'], '2026-11-01', '2026-08-01');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'from must be before to'], $response['body']);
    }

    public function test_list_transactions_rejects_same_from_and_to(): void
    {
        $user = $this->registerUser();

        $response = $this->listTransactionsBetween($user['token'], '2026-08-01', '2026-08-01');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'from must be before to'], $response['body']);
    }

    public function test_list_transactions_rejects_from_as_list(): void
    {
        $user = $this->registerUser();

        $response = $this->get('/transactions?from[]=2026-08-01&to=2026-11-01', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'from must be text'], $response['body']);
    }

    // Token check: requireUserId() in index.php answers 401 before list() runs

    public function test_list_transactions_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->get('/transactions');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    public function test_list_transactions_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->listTransactions($unknownToken);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    // Successful update: PUT /transactions/{id} replaces every field of the logged-in user's transaction

    public function test_update_transaction(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createCategory($user['token'], 'Bills');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->updateTransaction($user['token'], 1, [
            'category_id' => 2,
            'amount' => 20,
            'currency' => 'EUR',
            'date' => '2026-10-01',
            'payment_method' => 'cash',
            'description' => 'Dinner',
            'place' => 'Home',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'success' => true,
            'transaction' => [
                'id' => 1,
                'category_id' => 2,
                'amount' => '20.00',
                'currency' => 'EUR',
                'date' => '2026-10-01',
                'payment_method' => 'cash',
                'description' => 'Dinner',
                'place' => 'Home',
            ],
        ], $response['body']);
        $this->assertSame('20.00', $this->storedTransaction(1)['amount']);
        $this->assertSame(1, $this->countTransactions());
    }

    public function test_update_transaction_same_values(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->updateTransaction($user['token'], 1, $this->transactionBody());

        $this->assertSame(200, $response['status']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    public function test_update_transaction_clears_left_out_optional_fields(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->updateTransaction($user['token'], 1, [
            'category_id' => 1,
            'amount' => 12.5,
            'date' => '2026-10-12',
            'payment_method' => 'card',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertNull($this->storedTransaction(1)['description']);
        $this->assertNull($this->storedTransaction(1)['place']);
    }

    // Request body and fields: the same readTransaction() checks as create, answered with 400, nothing changes

    public function test_update_transaction_rejects_plain_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->putRaw('/transactions/1', 'hello', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    public function test_update_transaction_rejects_zero_amount(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->updateTransaction($user['token'], 1, $this->transactionBody(1, ['amount' => 0]));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'amount must be more than 0'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    public function test_update_transaction_rejects_other_users_category(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Rent');
        $this->createTransaction($secondUser['token'], $this->transactionBody(2));

        $response = $this->updateTransaction($secondUser['token'], 1, $this->transactionBody(1));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'category not found'], $response['body']);
        $this->assertSame(2, $this->storedTransaction(1)['category_id']);
    }

    // Not found: WHERE id AND user_id finds no row, answered with 404

    public function test_update_transaction_rejects_unknown_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->updateTransaction($user['token'], 999, $this->transactionBody(1, ['amount' => 20]));

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'transaction not found'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    public function test_update_transaction_rejects_other_users_transaction(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Rent');
        $this->createTransaction($firstUser['token'], $this->transactionBody(1));

        $response = $this->updateTransaction($secondUser['token'], 1, $this->transactionBody(2, ['amount' => 20]));

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'transaction not found'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
        $this->assertSame(1, $this->storedTransaction(1)['category_id']);
    }

    public function test_update_transaction_rejects_non_numeric_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->put('/transactions/abc', $this->transactionBody(1, ['amount' => 20]), [
            'Authorization: Bearer ' . $user['token'],
        ]);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'Not found'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    // Token check: requireUserId() in index.php answers 401 before update() runs

    public function test_update_transaction_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->put('/transactions/1', $this->transactionBody(1, ['amount' => 20]));

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    public function test_update_transaction_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->updateTransaction($unknownToken, 1, $this->transactionBody(1, ['amount' => 20]));

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame('12.50', $this->storedTransaction(1)['amount']);
    }

    // Successful delete: DELETE /transactions/{id} removes the logged-in user's transaction

    public function test_delete_transaction(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->deleteTransaction($user['token'], 1);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['success' => true], $response['body']);
        $this->assertSame(0, $this->countTransactions());
    }

    public function test_delete_transaction_only_deletes_this_transaction(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->deleteTransaction($user['token'], 1);

        $this->assertSame(200, $response['status']);
        $this->assertSame(1, $this->countTransactions());
        $this->assertFalse($this->storedTransaction(1));
        $this->assertNotFalse($this->storedTransaction(2));
    }

    public function test_delete_transaction_lets_category_be_deleted(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $blockedResponse = $this->deleteCategory($user['token'], 1);

        $this->assertSame(409, $blockedResponse['status']);

        $this->deleteTransaction($user['token'], 1);
        $response = $this->deleteCategory($user['token'], 1);

        $this->assertSame(200, $response['status']);
    }

    // Not found: WHERE id AND user_id finds no row, answered with 404

    public function test_delete_transaction_rejects_unknown_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->deleteTransaction($user['token'], 999);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'transaction not found'], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    public function test_delete_transaction_rejects_same_id_twice(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $firstResponse = $this->deleteTransaction($user['token'], 1);

        $this->assertSame(200, $firstResponse['status']);

        $secondResponse = $this->deleteTransaction($user['token'], 1);

        $this->assertSame(404, $secondResponse['status']);
        $this->assertSame(['error' => 'transaction not found'], $secondResponse['body']);
    }

    public function test_delete_transaction_rejects_other_users_transaction(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createTransaction($firstUser['token'], $this->transactionBody());

        $response = $this->deleteTransaction($secondUser['token'], 1);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'transaction not found'], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    public function test_delete_transaction_rejects_non_numeric_id(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->delete('/transactions/abc', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['error' => 'Not found'], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    // Token check: requireUserId() in index.php answers 401 before delete() runs

    public function test_delete_transaction_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->delete('/transactions/1');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    public function test_delete_transaction_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->deleteTransaction($unknownToken, 1);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countTransactions());
    }

    // Successful suggestions: GET /transactions/suggestions?q= returns the logged-in user's descriptions that start with q

    public function test_suggest_transactions_empty(): void
    {
        $user = $this->registerUser();

        $response = $this->suggestTransactions($user['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['suggestions' => []], $response['body']);
    }

    public function test_suggest_transactions(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->suggestTransactions($user['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'suggestions' => [
                [
                    'description' => 'Lunch',
                    'category_id' => 1,
                    'place' => 'Office',
                ],
            ],
        ], $response['body']);
    }

    public function test_suggest_transactions_matches_start_only(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Lunch']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Brunch']));

        $response = $this->suggestTransactions($user['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Lunch'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_ignores_case(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->suggestTransactions($user['token'], 'LU');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Lunch'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_trims_query(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->suggestTransactions($user['token'], '  lu  ');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Lunch'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_one_per_description(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $this->createTransaction($user['token'], $this->transactionBody());
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->suggestTransactions($user['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Lunch'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_most_used_first(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Car');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Garage']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Gas']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Gas']));

        $response = $this->suggestTransactions($user['token'], 'ga');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Gas', 'Garage'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_same_use_count_alphabetical(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Home');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Garden']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Garage']));

        $response = $this->suggestTransactions($user['token'], 'ga');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Garage', 'Garden'], array_column($response['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_uses_newest_category_and_place(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createCategory($user['token'], 'Car');
        $this->createTransaction($user['token'], $this->transactionBody(2, [
            'description' => 'Gas',
            'date' => '2026-10-05',
            'place' => 'OMV',
        ]));
        $this->createTransaction($user['token'], $this->transactionBody(1, [
            'description' => 'Gas',
            'date' => '2026-10-01',
            'place' => 'Shell',
        ]));

        $response = $this->suggestTransactions($user['token'], 'ga');

        $this->assertSame(200, $response['status']);
        $this->assertSame([
            'suggestions' => [
                [
                    'description' => 'Gas',
                    'category_id' => 2,
                    'place' => 'OMV',
                ],
            ],
        ], $response['body']);
    }

    public function test_suggest_transactions_at_most_eight(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');

        for ($i = 1; $i <= 10; $i++) {
            $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => "Lunch $i"]));
        }

        $response = $this->suggestTransactions($user['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertCount(8, $response['body']['suggestions']);
    }

    public function test_suggest_transactions_only_own(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createCategory($secondUser['token'], 'Food');
        $this->createTransaction($firstUser['token'], $this->transactionBody(1, ['description' => 'Lunch at work']));
        $this->createTransaction($secondUser['token'], $this->transactionBody(2, ['description' => 'Lunch at home']));

        $firstResponse = $this->suggestTransactions($firstUser['token'], 'lu');
        $secondResponse = $this->suggestTransactions($secondUser['token'], 'lu');

        $this->assertSame(['Lunch at work'], array_column($firstResponse['body']['suggestions'], 'description'));
        $this->assertSame(['Lunch at home'], array_column($secondResponse['body']['suggestions'], 'description'));
    }

    public function test_suggest_transactions_empty_for_other_user(): void
    {
        $firstUser = $this->registerUser('first@example.com');
        $secondUser = $this->registerUser('second@example.com');
        $this->createCategory($firstUser['token'], 'Food');
        $this->createTransaction($firstUser['token'], $this->transactionBody());

        $response = $this->suggestTransactions($secondUser['token'], 'lu');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['suggestions' => []], $response['body']);
    }

    // Wildcards: addcslashes() makes % and _ plain text, so they cannot match every description

    public function test_suggest_transactions_percent_is_plain_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => 'Lunch']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => '100% juice']));

        $response = $this->suggestTransactions($user['token'], '%');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['suggestions' => []], $response['body']);
    }

    public function test_suggest_transactions_underscore_is_plain_text(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->suggestTransactions($user['token'], '_unch');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['suggestions' => []], $response['body']);
    }

    public function test_suggest_transactions_matches_percent_in_description(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => '100% juice']));
        $this->createTransaction($user['token'], $this->transactionBody(1, ['description' => '1000 grams']));

        $response = $this->suggestTransactions($user['token'], '100%');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['100% juice'], array_column($response['body']['suggestions'], 'description'));
    }

    // Query: readText() needs q as text of at most 255 characters, answered with 400

    public function test_suggest_transactions_rejects_missing_query(): void
    {
        $user = $this->registerUser();

        $response = $this->get('/transactions/suggestions', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'q is required'], $response['body']);
    }

    public function test_suggest_transactions_rejects_whitespace_only_query(): void
    {
        $user = $this->registerUser();

        $response = $this->suggestTransactions($user['token'], '   ');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'q is required'], $response['body']);
    }

    public function test_suggest_transactions_rejects_query_too_long(): void
    {
        $user = $this->registerUser();

        $response = $this->suggestTransactions($user['token'], str_repeat('a', 256));

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'q must be at most 255 characters'], $response['body']);
    }

    public function test_suggest_transactions_rejects_query_as_list(): void
    {
        $user = $this->registerUser();

        $response = $this->get('/transactions/suggestions?q[]=lu', ['Authorization: Bearer ' . $user['token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'q must be text'], $response['body']);
    }

    // Token check: requireUserId() in index.php answers 401 before suggestions() runs

    public function test_suggest_transactions_rejects_missing_header(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());

        $response = $this->get('/transactions/suggestions?q=lu');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    public function test_suggest_transactions_rejects_unknown_token(): void
    {
        $user = $this->registerUser();
        $this->createCategory($user['token'], 'Food');
        $this->createTransaction($user['token'], $this->transactionBody());
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->suggestTransactions($unknownToken, 'lu');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
    }

    // Helpers: create the user and categories through the real endpoints, send the token as a Bearer header

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

    private function createCategory(string $token, string $name): void
    {
        $response = $this->post('/categories', ['name' => $name], ['Authorization: Bearer ' . $token]);

        $this->assertSame(201, $response['status']);
    }

    private function deleteCategory(string $token, int $id): array
    {
        return $this->delete('/categories/' . $id, ['Authorization: Bearer ' . $token]);
    }

    private function transactionBody(int $categoryId = 1, array $changes = []): array
    {
        return array_merge([
            'category_id' => $categoryId,
            'amount' => 12.5,
            'date' => '2026-10-12',
            'payment_method' => 'card',
            'description' => 'Lunch',
            'place' => 'Office',
        ], $changes);
    }

    private function createTransaction(string $token, array $body): array
    {
        return $this->post('/transactions', $body, ['Authorization: Bearer ' . $token]);
    }

    private function listTransactions(string $token): array
    {
        return $this->get('/transactions', ['Authorization: Bearer ' . $token]);
    }

    private function listTransactionsBetween(string $token, string $from, string $to): array
    {
        return $this->get('/transactions?from=' . $from . '&to=' . $to, ['Authorization: Bearer ' . $token]);
    }

    private function updateTransaction(string $token, int $id, array $body): array
    {
        return $this->put('/transactions/' . $id, $body, ['Authorization: Bearer ' . $token]);
    }

    private function deleteTransaction(string $token, int $id): array
    {
        return $this->delete('/transactions/' . $id, ['Authorization: Bearer ' . $token]);
    }

    private function suggestTransactions(string $token, string $query): array
    {
        return $this->get('/transactions/suggestions?q=' . urlencode($query), ['Authorization: Bearer ' . $token]);
    }

    private function storedTransaction(int $id): array|false
    {
        $stmt = self::$pdo->prepare(
            'SELECT user_id, category_id, amount, currency, date, payment_method, description, place
            FROM transactions WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    private function countTransactions(): int
    {
        return (int) self::$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    }
}
