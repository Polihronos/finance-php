<?php

namespace Tests;

class LoginTest extends ApiTestCase
{
    // Successful login: right email and password return 200 and a new session token

    public function test_login_returns_token(): void
    {
        $this->registerUser();

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertSame(1, $response['body']['user_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/', $response['body']['token']
        );
        $this->assertSame(2, $this->countSessions());
    }

    public function test_login_twice_creates_two_sessions(): void
    {
        $this->registerUser();

        $firstResponse = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $secondResponse = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(200, $firstResponse['status']);
        $this->assertSame(200, $secondResponse['status']);
        $this->assertNotSame($firstResponse['body']['token'], $secondResponse['body']['token']);
        $this->assertSame(3, $this->countSessions());
    }

    public function test_login_ignores_email_capitals(): void
    {
        $this->registerUser();

        $response = $this->post('/login', [
            'email' => 'TEST@Example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertSame(1, $response['body']['user_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/', $response['body']['token']
        );
        $this->assertSame(2, $this->countSessions());
    }

    // Request body: Request::json() must return a JSON object, otherwise 400

    public function test_login_rejects_plain_text(): void
    {
        $response = $this->postRaw('/login', 'hello');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_empty_body(): void
    {
        $response = $this->postRaw('/login', '');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_valid_json_but_not_object(): void
    {
        $response = $this->postRaw('/login', '"hello"');

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'request body must be JSON'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    // Required fields, missing or not text: caught by the foreach (isset, is_string)

    public function test_login_rejects_missing_email_field(): void
    {
        $response = $this->post('/login', [
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email is required'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_missing_password_field(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password is required'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_email_as_null(): void
    {
        $response = $this->post('/login', [
            'email' => null,
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email is required'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_email_as_list(): void
    {
        $response = $this->post('/login', [
            'email' => ['test@example.com'],
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email must be text'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_email_as_number(): void
    {
        $response = $this->post('/login', [
            'email' => 123,
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'email must be text'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_password_as_list(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => ['testpassword'],
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password must be text'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    // Wrong credentials: unknown email and wrong password get the same 401 answer

    public function test_login_rejects_wrong_password(): void
    {
        $this->registerUser();

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'invalid email or password'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_login_rejects_unknown_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'invalid email or password'], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_login_rejects_empty_password(): void
    {
        $this->registerUser();

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'invalid email or password'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_login_rejects_empty_email(): void
    {
        $this->registerUser();

        $response = $this->post('/login', [
            'email' => '',
            'password' => 'testpassword',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'invalid email or password'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    // Helper: creates the user the login tests log in as, through the real register endpoint

    private function registerUser(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $response['status']);
    }
}
