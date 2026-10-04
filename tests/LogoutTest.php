<?php

namespace Tests;

class LogoutTest extends ApiTestCase
{
    // Successful logout: a valid token deletes exactly its own session

    public function test_logout_deletes_session(): void
    {
        $token = $this->registerUser();

        $response = $this->post('/logout', [], ['Authorization: Bearer ' . $token]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['success' => true], $response['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_logout_only_deletes_this_session(): void
    {
        $registerToken = $this->registerUser();
        $loginToken = $this->loginUser();

        $response = $this->post('/logout', [], ['Authorization: Bearer ' . $loginToken]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['success' => true], $response['body']);
        $this->assertSame(1, $this->countSessions());

        $otherResponse = $this->post('/logout', [], ['Authorization: Bearer ' . $registerToken]);

        $this->assertSame(200, $otherResponse['status']);
        $this->assertSame(0, $this->countSessions());
    }

    // Token check: requireUserId() answers 401 for a missing, malformed, unknown or expired token

    public function test_logout_rejects_missing_header(): void
    {
        $this->registerUser();

        $response = $this->post('/logout', []);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_logout_rejects_header_without_bearer(): void
    {
        $token = $this->registerUser();

        $response = $this->post('/logout', [], ['Authorization: ' . $token]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_logout_rejects_malformed_token(): void
    {
        $this->registerUser();

        $response = $this->post('/logout', [], ['Authorization: Bearer abc123']);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_logout_rejects_unknown_token(): void
    {
        $this->registerUser();
        $unknownToken = bin2hex(random_bytes(32));

        $response = $this->post('/logout', [], ['Authorization: Bearer ' . $unknownToken]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    public function test_logout_rejects_same_token_twice(): void
    {
        $token = $this->registerUser();

        $firstResponse = $this->post('/logout', [], ['Authorization: Bearer ' . $token]);

        $this->assertSame(200, $firstResponse['status']);

        $secondResponse = $this->post('/logout', [], ['Authorization: Bearer ' . $token]);

        $this->assertSame(401, $secondResponse['status']);
        $this->assertSame(['error' => 'not logged in'], $secondResponse['body']);
        $this->assertSame(0, $this->countSessions());
    }

    public function test_logout_rejects_expired_token(): void
    {
        $registerToken = $this->registerUser();
        $this->loginUser();

        $stmt = self::$pdo->prepare(
            'UPDATE sessions SET expires_at = NOW() - INTERVAL 1 DAY WHERE token_hash = :token_hash'
        );
        $stmt->execute(['token_hash' => hash('sha256', $registerToken)]);

        $response = $this->post('/logout', [], ['Authorization: Bearer ' . $registerToken]);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['error' => 'not logged in'], $response['body']);
        $this->assertSame(1, $this->countSessions());
    }

    // Helpers: create the user and its sessions through the real register and login endpoints

    private function registerUser(): string
    {
        $response = $this->post('/register', [
            'name' => 'TestName',
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(201, $response['status']);

        return $response['body']['token'];
    }

    private function loginUser(): string
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'testpassword',
        ]);

        $this->assertSame(200, $response['status']);

        return $response['body']['token'];
    }
}
