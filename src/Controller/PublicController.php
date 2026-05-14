<?php

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Repository\PlaylistRepository;
use App\Service\RequestLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    public function __construct(
        private PlaylistRepository $playlistRepo,
        private ChannelRepository $channelRepo,
        private RequestLogService $requestLogService,
    ) {}

    #[Route('/lists/{slug}', name: 'public_playlist', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $playlist = $this->playlistRepo->findOneBy(['slug' => $slug]);

        if (!$playlist) {
            return new Response('Playlist not found', 404, ['Content-Type' => 'text/plain']);
        }

        $this->requestLogService->log($request, $playlist);

        $channels = $this->channelRepo->findBy(
            ['playlist' => $playlist, 'enabled' => true],
            ['position' => 'ASC']
        );

        $lines = ['#EXTM3U'];
        foreach ($channels as $channel) {
            $attrs = '#EXTINF:-1';
            if ($channel->getTvgId() !== null) {
                $attrs .= ' tvg-id="' . str_replace('"', '', $channel->getTvgId()) . '"';
            }
            if ($channel->getTvgName() !== null) {
                $attrs .= ' tvg-name="' . str_replace('"', '', $channel->getTvgName()) . '"';
            }
            if ($channel->getTvgLogo() !== null) {
                $attrs .= ' tvg-logo="' . str_replace('"', '', $channel->getTvgLogo()) . '"';
            }
            $attrs .= ',' . $channel->getName();
            $lines[] = $attrs;
            $lines[] = $channel->getUrl();
        }

        return new Response(
            implode("\n", $lines) . "\n",
            200,
            ['Content-Type' => 'application/x-mpegurl; charset=utf-8']
        );
    }
}
