<?php

namespace App\Service;

use App\Repository\PlaylistRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SlugGenerator
{
    private AsciiSlugger $slugger;

    public function __construct(private PlaylistRepository $repo)
    {
        $this->slugger = new AsciiSlugger();
    }

    public function fromName(string $name): string
    {
        $slug = strtolower((string) $this->slugger->slug($name));
        return $slug !== '' ? $slug : 'playlist';
    }

    public function isAvailable(string $slug, ?string $excludeId = null): bool
    {
        $qb = $this->repo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :id')
               ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }
}
