<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class PublicControllerTest extends ApiTestCase
{
    private function createPlaylist(string $name): string
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => $name], 'sample.m3u8');
        return $this->responseData()['slug'];
    }

    public function testShow_success(): void
    {
        $slug = $this->createPlaylist('Public Test');

        $this->client->request('GET', "/lists/{$slug}");

        $this->assertSame(200, $this->statusCode());
        $content = $this->client->getResponse()->getContent();
        $this->assertStringStartsWith('#EXTM3U', $content);
        $this->assertStringContainsString('#EXTINF', $content);
        $this->assertStringContainsString('Channel One', $content);
        $this->assertStringContainsString('http://example.com/stream1.m3u8', $content);
        $this->assertStringContainsString('application/x-mpegurl', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testShow_onlyEnabledChannels(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Partial List'], 'sample.m3u8');
        $playlist = $this->responseData();

        // Disable the first channel
        $this->jsonRequest('GET', "/api/lists/{$playlist['id']}/channels");
        $channels = $this->responseData();
        $this->jsonRequest('PATCH', "/api/lists/{$playlist['id']}/channels", [
            ['id' => $channels[0]['id'], 'enabled' => false],
        ]);

        $this->client->request('GET', "/lists/{$playlist['slug']}");
        $content = $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('Channel One', $content);
        $this->assertStringContainsString('Channel Two', $content);
    }

    public function testShow_notFound(): void
    {
        $this->client->request('GET', '/lists/nonexistent-slug');

        $this->assertSame(404, $this->statusCode());
    }

    public function testShow_logsRequest(): void
    {
        $this->loginAsAdmin();
        $this->uploadRequest('/api/lists', ['name' => 'Logged List'], 'sample.m3u8');
        $playlist = $this->responseData();
        $id = $playlist['id'];
        $slug = $playlist['slug'];

        $this->client->request('GET', "/lists/{$slug}");

        $this->loginAsAdmin();
        $this->jsonRequest('GET', "/api/lists/{$id}/logs");
        $logs = $this->responseData();

        $this->assertSame(1, $logs['total']);
    }
}
