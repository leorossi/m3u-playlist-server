<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class ProfileControllerTest extends ApiTestCase
{
    public function testChangePassword_success(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('PUT', '/api/profile/password', [
            'currentPassword' => 'password',
            'newPassword'     => 'newpassword123',
        ]);

        $this->assertSame(200, $this->statusCode());
        $this->assertArrayHasKey('message', $this->responseData());
    }

    public function testChangePassword_wrongCurrent(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('PUT', '/api/profile/password', [
            'currentPassword' => 'wrong',
            'newPassword'     => 'newpassword123',
        ]);

        $this->assertSame(400, $this->statusCode());
        $this->assertSame('Current password is incorrect', $this->responseData()['error']);
    }

    public function testChangePassword_missingCurrentPassword(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('PUT', '/api/profile/password', [
            'newPassword' => 'newpassword123',
        ]);

        $this->assertSame(400, $this->statusCode());
    }

    public function testChangePassword_missingNewPassword(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('PUT', '/api/profile/password', [
            'currentPassword' => 'password',
        ]);

        $this->assertSame(400, $this->statusCode());
    }

    public function testChangePassword_unauthenticated(): void
    {
        $this->jsonRequest('PUT', '/api/profile/password', [
            'currentPassword' => 'password',
            'newPassword'     => 'new',
        ]);

        $this->assertSame(401, $this->statusCode());
    }
}
