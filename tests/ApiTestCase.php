<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Ensure upload dir exists in test env
        $uploadDir = static::getContainer()->getParameter('upload_dir');
        if (!is_dir((string) $uploadDir)) {
            mkdir((string) $uploadDir, 0777, true);
        }

        // Recreate schema from scratch for isolation
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        $connection = $this->em->getConnection();
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->adminUser = new User();
        $this->adminUser->setEmail('admin@test.com');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->adminUser->setPassword($hasher->hashPassword($this->adminUser, 'password'));
        $this->em->persist($this->adminUser);

        $this->regularUser = new User();
        $this->regularUser->setEmail('user@test.com');
        $this->regularUser->setRoles([]);
        $this->regularUser->setPassword($hasher->hashPassword($this->regularUser, 'password'));
        $this->em->persist($this->regularUser);

        $this->em->flush();
    }

    protected function loginAsAdmin(): void
    {
        $this->client->loginUser($this->adminUser);
    }

    protected function loginAsUser(): void
    {
        $this->client->loginUser($this->regularUser);
    }

    protected function jsonRequest(string $method, string $uri, array $data = []): void
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data ? json_encode($data) : null
        );
    }

    protected function uploadRequest(string $uri, array $params, string $fixtureName): void
    {
        $src = __DIR__ . '/Fixtures/' . $fixtureName;
        $tmp = tempnam(sys_get_temp_dir(), 'test_m3u_');
        copy($src, $tmp);

        $this->client->request(
            'POST',
            $uri,
            $params,
            ['file' => new UploadedFile($tmp, $fixtureName, 'audio/x-mpegurl', null, true)]
        );
    }

    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    protected function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }
}
