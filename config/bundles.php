<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
];

// Guard dev-only bundles: class_exists returns false (without throwing) when
// the package is absent, e.g. after `composer install --no-dev`.
if (class_exists(\Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class)) {
    $bundles[\Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class] = ['dev' => true, 'test' => true];
}

if (class_exists(\Symfony\Bundle\MakerBundle\MakerBundle::class)) {
    $bundles[\Symfony\Bundle\MakerBundle\MakerBundle::class] = ['dev' => true];
}

return $bundles;
