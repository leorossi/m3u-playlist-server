<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class UserControllerTest extends ApiTestCase
{
    // ── GET /api/users ───────────────────────────────────────────────────────

    public function testIndex_asAdmin(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('GET', '/api/users');

        $this->assertSame(200, $this->statusCode());
        $data = $this->responseData();
        $this->assertIsArray($data);
        $this->assertCount(2, $data); // admin + regular
    }

    public function testIndex_asUser_forbidden(): void
    {
        $this->loginAsUser();
        $this->jsonRequest('GET', '/api/users');

        $this->assertSame(403, $this->statusCode());
    }

    public function testIndex_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/users');

        $this->assertSame(401, $this->statusCode());
    }

    // ── POST /api/users ──────────────────────────────────────────────────────

    public function testCreate_success(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('POST', '/api/users', [
            'email'    => 'new@test.com',
            'password' => 'secret123',
            'roles'    => ['ROLE_USER'],
        ]);

        $this->assertSame(201, $this->statusCode());
        $data = $this->responseData();
        $this->assertSame('new@test.com', $data['email']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function testCreate_duplicateEmail(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('POST', '/api/users', [
            'email'    => 'admin@test.com',
            'password' => 'secret',
            'roles'    => [],
        ]);

        $this->assertSame(409, $this->statusCode());
    }

    public function testCreate_missingFields(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('POST', '/api/users', ['email' => 'incomplete@test.com']);

        $this->assertSame(400, $this->statusCode());
    }

    public function testCreate_invalidEmail(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('POST', '/api/users', ['email' => 'not-an-email', 'password' => 'secret']);

        $this->assertSame(400, $this->statusCode());
    }

    // ── GET /api/users/{id} ──────────────────────────────────────────────────

    public function testShow_success(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->regularUser->getId();
        $this->jsonRequest('GET', "/api/users/{$id}");

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('user@test.com', $this->responseData()['email']);
    }

    // ── PUT /api/users/{id} ──────────────────────────────────────────────────

    public function testUpdate_success(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->regularUser->getId();
        $this->jsonRequest('PUT', "/api/users/{$id}", [
            'email' => 'updated@test.com',
            'roles' => ['ROLE_USER'],
        ]);

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('updated@test.com', $this->responseData()['email']);
    }

    public function testUpdate_emailConflict(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->regularUser->getId();
        $this->jsonRequest('PUT', "/api/users/{$id}", ['email' => 'admin@test.com']);

        $this->assertSame(409, $this->statusCode());
    }

    // ── DELETE /api/users/{id} ───────────────────────────────────────────────

    public function testDelete_success(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->regularUser->getId();
        $this->jsonRequest('DELETE', "/api/users/{$id}");

        $this->assertSame(204, $this->statusCode());
    }

    public function testDelete_selfDelete(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->adminUser->getId();
        $this->jsonRequest('DELETE', "/api/users/{$id}");

        $this->assertSame(400, $this->statusCode());
    }
}
