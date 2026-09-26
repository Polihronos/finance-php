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
}
