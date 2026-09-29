<?php

namespace Tests;

class RegisterTest extends ApiTestCase
{
    // Successful registration: valid input creates the user and returns 201

    public function test_register_create_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame(['success' => true, 'user_id' => 1], $response['body']);
        $this->assertSame(1, $this->countUsers());
    }

    public function test_register_create_user_password_8(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '12345678',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame(['success' => true, 'user_id' => 1], $response['body']);
        $this->assertSame(1, $this->countUsers());
    }

    public function test_register_create_user_password_36(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '123456789012345678901234567890123456',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame(['success' => true, 'user_id' => 1], $response['body']);
        $this->assertSame(1, $this->countUsers());
    }

    // Request body: Request::json() must return a JSON object, otherwise 400

    public function test_register_rejects_plain_text(): void
    {
        $response = $this->postRaw('/register', 'hello');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_empty_body(): void
    {
        $response = $this->postRaw('/register', '');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_valid_json_but_not_object(): void
    {
        $response = $this->postRaw('/register', '"hello"');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    // Required fields, missing or not text: caught by the foreach (isset, is_string)

    public function test_register_rejects_missing_name_field(): void
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_missing_email_field(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_missing_password_field(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_password_as_list(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => ['testpassword']
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => "password must be text"], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_name_as_number(): void
    {
        $response = $this->post('/register', [
            'name' => 1234,
            'email' => 'test@example.com',
            'password' => 'testpassword'
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => "name must be text"], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_name_as_null(): void
    {
        $response = $this->post('/register', [
            'name' => null,
            'email' => 'test@example.com',
            'password' => 'testpassword'
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    // Required fields, empty: caught by empty() after mb_trim

    public function test_register_rejects_whitespace_only_name(): void
    {
        $response = $this->post('/register', [
            'name' => ' ',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_empty_name(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_empty_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => '',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_empty_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password is required'], $response['body']);
$this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_all_empty_fields(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name is required'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    // Email format: filter_var(FILTER_VALIDATE_EMAIL) must accept the email

    public function test_register_rejects_email_invalid(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'test@.@bg',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'invalid email'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    // Password length: between 8 and 36 characters

    public function test_register_rejects_password_too_short(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '1234567',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password must be at least 8 characters'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    public function test_register_rejects_password_too_long(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '1234567890123456789012345678901234567',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password must be less than 36 characters'], $response['body']);
        $this->assertSame(0, $this->countUsers());
    }

    // Duplicate email: the UNIQUE email column makes the insert fail, answered with 409

    public function test_register_rejects_duplicate_email(): void
    {
        $firstResponse = $this->post('/register', [
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $firstResponse['status']);
        $this->assertSame(1, $this->countUsers());

        $secondResponse = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'duplicate@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(409, $secondResponse['status']);
        $this->assertSame(['error' => 'email already registered'], $secondResponse['body']);
        $this->assertSame(1, $this->countUsers());
    }
}
