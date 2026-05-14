<?php

namespace App\Entity;

use App\Repository\ChannelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\Table(name: 'channels')]
class Channel
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'channels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Playlist $playlist;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tvgId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tvgName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tvgLogo = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlaylist(): Playlist
    {
        return $this->playlist;
    }

    public function setPlaylist(Playlist $playlist): static
    {
        $this->playlist = $playlist;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getTvgId(): ?string
    {
        return $this->tvgId;
    }

    public function setTvgId(?string $tvgId): static
    {
        $this->tvgId = $tvgId;
        return $this;
    }

    public function getTvgName(): ?string
    {
        return $this->tvgName;
    }

    public function setTvgName(?string $tvgName): static
    {
        $this->tvgName = $tvgName;
        return $this;
    }

    public function getTvgLogo(): ?string
    {
        return $this->tvgLogo;
    }

    public function setTvgLogo(?string $tvgLogo): static
    {
        $this->tvgLogo = $tvgLogo;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
