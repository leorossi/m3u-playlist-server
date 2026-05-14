<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(array_map(
            $this->serializeUser(...),
            $this->userRepository->findAll()
        ));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json($this->serializeUser($user));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email already in use'], 409);
        }

        $validRoles = ['ROLE_USER', 'ROLE_ADMIN'];
        $roles = array_values(array_filter(
            $data['roles'] ?? [],
            fn($r) => in_array($r, $validRoles, true)
        ));

        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        return $this->json($this->serializeUser($user), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Invalid email format'], 400);
            }
            $existing = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existing && (string) $existing->getId() !== (string) $user->getId()) {
                return $this->json(['error' => 'Email already in use'], 409);
            }
            $user->setEmail($data['email']);
        }

        if (!empty($data['password'])) {
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));
        }

        if (isset($data['roles'])) {
            $validRoles = ['ROLE_USER', 'ROLE_ADMIN'];
            $roles = array_values(array_filter(
                $data['roles'],
                fn($r) => in_array($r, $validRoles, true)
            ));
            $user->setRoles($roles);
        }

        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        if ((string) $user->getId() === (string) $this->getUser()->getId()) {
            return $this->json(['error' => 'Cannot delete your own account'], 400);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => (string) $user->getId(),
            'email'     => $user->getEmail(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
