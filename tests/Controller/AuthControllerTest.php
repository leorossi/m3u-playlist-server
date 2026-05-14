<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testLogin_success(): void
    {
        $this->client->disableReboot();
        $this->jsonRequest('POST', '/api/login', ['email' => 'admin@test.com', 'password' => 'password']);

        $this->assertSame(200, $this->statusCode());
        $data = $this->responseData();
        $this->assertSame('admin@test.com', $data['email']);
        $this->assertContains('ROLE_ADMIN', $data['roles']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testLogin_wrongPassword(): void
    {
        $this->jsonRequest('POST', '/api/login', ['email' => 'admin@test.com', 'password' => 'wrong']);

        $this->assertSame(401, $this->statusCode());
        $this->assertArrayHasKey('error', $this->responseData());
    }

    public function testLogin_unknownEmail(): void
    {
        $this->jsonRequest('POST', '/api/login', ['email' => 'nobody@test.com', 'password' => 'password']);

        $this->assertSame(401, $this->statusCode());
    }

    public function testMe_authenticated(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('GET', '/api/me');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('admin@test.com', $this->responseData()['email']);
    }

    public function testMe_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/me');

        $this->assertSame(401, $this->statusCode());
    }

    public function testLogout_success(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('POST', '/api/logout');

        $this->assertSame(200, $this->statusCode());
        $this->assertArrayHasKey('message', $this->responseData());
    }
}
