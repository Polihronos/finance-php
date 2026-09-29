<?php

namespace Tests;

class RegisterTest extends ApiTestCase
{
    public function test_register_create_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame(['success' => true, 'user_id' => 1], $response['body']);
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
    }

    public function test_register_create_user_password_39(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '123456789012345678901234567890123456789',
        ]);

        $this->assertSame(201, $response['status']);
        $this->assertSame(['success' => true, 'user_id' => 1], $response['body']);
    }

    public function test_register_rejects_whitespace_only_name(): void
    {
        $response = $this->post('/register', [
            'name' => ' ',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name, email and password are required'], $response['body']);
    }

    public function test_register_rejects_empty_name(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'Testemail@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name, email and password are required'], $response['body']);
    }

    public function test_register_rejects_empty_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => '',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name, email and password are required'], $response['body']);
    }

    public function test_register_rejects_empty_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name, email and password are required'], $response['body']);
    }

    public function test_register_rejects_all_empty_fields(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'name, email and password are required'], $response['body']);
    }

    public function test_register_rejects_email_invalid(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'test@.@bg',
            'password' => 'testpassword',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'invalid email'], $response['body']);
    }

    public function test_register_rejects_password_too_short(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '1234567',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password must be at least 8 characters'], $response['body']);
    }

    public function test_register_rejects_password_too_long(): void
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'Testemail@example.com',
            'password' => '1234567890123456789012345678901234567890',
        ]);

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'password must be less than 40 characters'], $response['body']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $firstResponse = $this->post('/register', [
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $firstResponse['status']);

        $secondResponse = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'duplicate@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(409, $secondResponse['status']);
        $this->assertSame(['error' => 'email already registered'], $secondResponse['body']);
    }
}
