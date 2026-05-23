<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Playlist;
use App\Entity\User;
use App\Repository\ChannelRepository;
use App\Repository\PlaylistRepository;
use App\Repository\RequestLogRepository;
use App\Security\PlaylistVoter;
use App\Service\M3uParser;
use App\Service\SlugGenerator;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/lists')]
#[IsGranted('ROLE_USER')]
class PlaylistController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlaylistRepository $playlistRepo,
        private ChannelRepository $channelRepo,
        private RequestLogRepository $logRepo,
        private SlugGenerator $slugGenerator,
        private M3uParser $m3uParser,
        #[Autowire('%upload_dir%')] private string $uploadDir,
    ) {}

    // ── Playlists ────────────────────────────────────────────────────────────

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $playlists = in_array('ROLE_ADMIN', $user->getRoles(), true)
            ? $this->playlistRepo->findBy([], ['createdAt' => 'DESC'])
            : $this->playlistRepo->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->json(array_map($this->serializePlaylist(...), $playlists));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $file = $request->files->get('file');

        if ($name === '') {
            return $this->json(['error' => 'Name is required'], 400);
        }

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'A valid M3U file is required'], 400);
        }

        $slug = $this->slugGenerator->fromName($name);

        if (!$this->slugGenerator->isAvailable($slug)) {
            return $this->json([
                'error' => 'A playlist with this slug already exists. Choose a different name or edit the slug.',
                'slug'  => $slug,
            ], 409);
        }

        $filename = uniqid('', true) . '.m3u8';
        $file->move($this->uploadDir, $filename);
        $fullPath = $this->uploadDir . '/' . $filename;

        $channels = $this->m3uParser->parse((string) file_get_contents($fullPath));

        if (empty($channels)) {
            unlink($fullPath);
            return $this->json(['error' => 'No channels found in the uploaded file'], 422);
        }

        /** @var User $user */
        $user = $this->getUser();

        $playlist = new Playlist();
        $playlist->setUser($user);
        $playlist->setName($name);
        $playlist->setSlug($slug);
        $playlist->setFilePath('var/uploads/' . $filename);
        $this->em->persist($playlist);

        foreach ($channels as $pos => $data) {
            $channel = new Channel();
            $channel->setPlaylist($playlist);
            $channel->setPosition($pos + 1);
            $channel->setName($data['name']);
            $channel->setUrl($data['url']);
            $channel->setTvgId($data['tvgId']);
            $channel->setTvgName($data['tvgName']);
            $channel->setTvgLogo($data['tvgLogo']);
            $channel->setEnabled(true);
            $this->em->persist($channel);
        }

        $this->em->flush();
        $this->em->refresh($playlist);

        return $this->json($this->serializePlaylist($playlist), 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Playlist $playlist): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        return $this->json($this->serializePlaylist($playlist));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Playlist $playlist, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $playlist->setName((string) $data['name']);
        }

        if (isset($data['slug'])) {
            $newSlug = trim((string) $data['slug']);
            if ($newSlug === '') {
                return $this->json(['error' => 'Slug cannot be empty'], 400);
            }
            if ($newSlug !== $playlist->getSlug() && !$this->slugGenerator->isAvailable($newSlug, (string) $playlist->getId())) {
                return $this->json(['error' => 'Slug already in use'], 409);
            }
            $playlist->setSlug($newSlug);
        }

        $this->em->flush();

        return $this->json($this->serializePlaylist($playlist));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Playlist $playlist): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        // Remove uploaded file from disk
        $filePath = $this->uploadDir . '/' . basename($playlist->getFilePath());
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->em->remove($playlist);
        $this->em->flush();

        return $this->json(null, 204);
    }

    // ── Channels ─────────────────────────────────────────────────────────────

    #[Route('/{id}/channels', methods: ['GET'])]
    public function channels(Playlist $playlist): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        return $this->json(array_map(
            $this->serializeChannel(...),
            $playlist->getChannels()->toArray()
        ));
    }

    #[Route('/{id}/channels', methods: ['PATCH'])]
    public function updateChannels(Playlist $playlist, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        $data = json_decode($request->getContent(), true) ?? [];
        if (!is_array($data)) {
            return $this->json(['error' => 'Expected array of {id, enabled} objects'], 400);
        }

        $updates = [];
        foreach ($data as $item) {
            if (isset($item['id'], $item['enabled'])) {
                $updates[$item['id']] = (bool) $item['enabled'];
            }
        }

        foreach ($playlist->getChannels() as $channel) {
            $id = (string) $channel->getId();
            if (array_key_exists($id, $updates)) {
                $channel->setEnabled($updates[$id]);
            }
        }

        $this->em->flush();

        return $this->json(array_map(
            $this->serializeChannel(...),
            $playlist->getChannels()->toArray()
        ));
    }

    #[Route('/{id}/channels/domain', methods: ['PUT'])]
    public function updateChannelsDomain(Playlist $playlist, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        $data      = json_decode($request->getContent(), true) ?? [];
        $newDomain = trim((string) ($data['domain'] ?? ''));

        if ($newDomain === '') {
            return $this->json(['error' => 'Domain cannot be empty'], 400);
        }

        foreach ($playlist->getChannels() as $channel) {
            $parsed = parse_url($channel->getUrl());
            if (!$parsed || !isset($parsed['scheme'])) {
                continue;
            }

            $newUrl = $parsed['scheme'] . '://' . $newDomain;
            if (isset($parsed['path'])) {
                $newUrl .= $parsed['path'];
            }
            if (isset($parsed['query'])) {
                $newUrl .= '?' . $parsed['query'];
            }
            if (isset($parsed['fragment'])) {
                $newUrl .= '#' . $parsed['fragment'];
            }

            $channel->setUrl($newUrl);
        }

        $this->em->flush();

        return $this->json(array_map(
            $this->serializeChannel(...),
            $playlist->getChannels()->toArray()
        ));
    }

    // ── Logs ─────────────────────────────────────────────────────────────────

    #[Route('/{id}/logs', methods: ['GET'])]
    public function logs(Playlist $playlist, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PlaylistVoter::MANAGE, $playlist);

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 20)));

        $logs  = $this->logRepo->findBy(['playlist' => $playlist], ['requestedAt' => 'DESC'], $limit, ($page - 1) * $limit);
        $total = $this->logRepo->count(['playlist' => $playlist]);

        return $this->json([
            'data'  => array_map($this->serializeLog(...), $logs),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // ── Serializers ──────────────────────────────────────────────────────────

    private function serializePlaylist(Playlist $p): array
    {
        $channels = $p->getChannels();
        $enabled  = $channels->matching(Criteria::create()->where(Criteria::expr()->eq('enabled', true)));

        return [
            'id'           => (string) $p->getId(),
            'name'         => $p->getName(),
            'slug'         => $p->getSlug(),
            'channelCount' => $channels->count(),
            'enabledCount' => $enabled->count(),
            'createdAt'    => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'    => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeChannel(Channel $c): array
    {
        return [
            'id'       => (string) $c->getId(),
            'position' => $c->getPosition(),
            'name'     => $c->getName(),
            'url'      => $c->getUrl(),
            'tvgId'    => $c->getTvgId(),
            'tvgName'  => $c->getTvgName(),
            'tvgLogo'  => $c->getTvgLogo(),
            'enabled'  => $c->isEnabled(),
        ];
    }

    private function serializeLog(\App\Entity\RequestLog $l): array
    {
        return [
            'id'          => (string) $l->getId(),
            'requestedAt' => $l->getRequestedAt()->format(\DateTimeInterface::ATOM),
            'ipAddress'   => $l->getIpAddress(),
            'userAgent'   => $l->getUserAgent(),
            'headers'     => $l->getHeaders(),
        ];
    }
}
