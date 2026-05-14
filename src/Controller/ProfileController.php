<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['currentPassword'])) {
            return $this->json(['error' => 'Current password is required'], 400);
        }

        if (!$this->hasher->isPasswordValid($user, (string) $data['currentPassword'])) {
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        if (empty($data['newPassword'])) {
            return $this->json(['error' => 'New password is required'], 400);
        }

        $user->setPassword($this->hasher->hashPassword($user, (string) $data['newPassword']));
        $this->em->flush();

        return $this->json(['message' => 'Password updated successfully']);
    }
}
