<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class PlaylistControllerTest extends ApiTestCase
{
    // ── GET /api/lists ───────────────────────────────────────────────────────

    public function testIndex_empty(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('GET', '/api/lists');

        $this->assertSame(200, $this->statusCode());
        $this->assertSame([], $this->responseData());
    }

    public function testIndex_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/lists');

        $this->assertSame(401, $this->statusCode());
    }

    // ── POST /api/lists ──────────────────────────────────────────────────────

    public function testCreate_success(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Test Playlist'], 'sample.m3u8');

        $this->assertSame(201, $this->statusCode());
        $data = $this->responseData();
        $this->assertSame('Test Playlist', $data['name']);
        $this->assertSame('test-playlist', $data['slug']);
        $this->assertSame(3, $data['channelCount']);
        $this->assertSame(3, $data['enabledCount']);
    }

    public function testCreate_missingName(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => ''], 'sample.m3u8');

        $this->assertSame(400, $this->statusCode());
    }

    public function testCreate_missingFile(): void
    {
        $this->loginAsAdmin();
        $this->client->request('POST', '/api/lists', ['name' => 'No File']);

        $this->assertSame(400, $this->statusCode());
    }

    public function testCreate_slugConflict(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Test Playlist'], 'sample.m3u8');
        $this->assertSame(201, $this->statusCode());

        $this->uploadRequest('/api/lists', ['name' => 'Test Playlist'], 'sample.m3u8');
        $this->assertSame(409, $this->statusCode());
    }

    // ── GET /api/lists/{id} ──────────────────────────────────────────────────

    public function testShow_success(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'My List'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('GET', "/api/lists/{$id}");

        $this->assertSame(200, $this->statusCode());
        $this->assertSame('My List', $this->responseData()['name']);
    }

    public function testShow_notFound(): void
    {
        $this->loginAsAdmin();
        $this->jsonRequest('GET', '/api/lists/00000000-0000-0000-0000-000000000000');

        $this->assertSame(404, $this->statusCode());
    }

    public function testShow_anotherUser_forbidden(): void
    {
        // Admin creates a playlist
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Admin List'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        // Regular user tries to access it
        $this->loginAsUser();
        $this->jsonRequest('GET', "/api/lists/{$id}");

        $this->assertSame(403, $this->statusCode());
    }

    // ── PUT /api/lists/{id} ──────────────────────────────────────────────────

    public function testUpdate_success(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Old Name'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('PUT', "/api/lists/{$id}", ['name' => 'New Name', 'slug' => 'new-slug']);

        $this->assertSame(200, $this->statusCode());
        $data = $this->responseData();
        $this->assertSame('New Name', $data['name']);
        $this->assertSame('new-slug', $data['slug']);
    }

    public function testUpdate_slugConflict(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'List One'], 'sample.m3u8');
        $this->uploadRequest('/api/lists', ['name' => 'List Two'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('PUT', "/api/lists/{$id}", ['slug' => 'list-one']);

        $this->assertSame(409, $this->statusCode());
    }

    // ── DELETE /api/lists/{id} ───────────────────────────────────────────────

    public function testDelete_success(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'To Delete'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('DELETE', "/api/lists/{$id}");
        $this->assertSame(204, $this->statusCode());

        $this->jsonRequest('GET', "/api/lists/{$id}");
        $this->assertSame(404, $this->statusCode());
    }

    // ── GET /api/lists/{id}/channels ─────────────────────────────────────────

    public function testChannels_get(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Channel List'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('GET', "/api/lists/{$id}/channels");

        $this->assertSame(200, $this->statusCode());
        $channels = $this->responseData();
        $this->assertCount(3, $channels);
        $this->assertSame('Channel One', $channels[0]['name']);
        $this->assertTrue($channels[0]['enabled']);
    }

    // ── PATCH /api/lists/{id}/channels ───────────────────────────────────────

    public function testChannels_patch(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Toggle List'], 'sample.m3u8');
        $playlist = $this->responseData();

        $this->jsonRequest('GET', "/api/lists/{$playlist['id']}/channels");
        $channels = $this->responseData();
        $firstId = $channels[0]['id'];

        $this->jsonRequest('PATCH', "/api/lists/{$playlist['id']}/channels", [
            ['id' => $firstId, 'enabled' => false],
        ]);

        $this->assertSame(200, $this->statusCode());
        $updated = $this->responseData();
        $disabled = array_filter($updated, fn($c) => $c['id'] === $firstId);
        $this->assertFalse(array_values($disabled)[0]['enabled']);
    }

    public function testChannels_patch_invalidBody(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Bad Patch'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('PATCH', "/api/lists/{$id}/channels", ['not' => 'an array of items']);

        // Missing id/enabled keys are just ignored, still 200
        $this->assertSame(200, $this->statusCode());
    }

    // ── GET /api/lists/{id}/logs ─────────────────────────────────────────────

    public function testLogs_empty(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Log List'], 'sample.m3u8');
        $id = $this->responseData()['id'];

        $this->jsonRequest('GET', "/api/lists/{$id}/logs");

        $this->assertSame(200, $this->statusCode());
        $data = $this->responseData();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertSame(0, $data['total']);
    }
}
