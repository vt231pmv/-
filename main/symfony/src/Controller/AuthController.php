<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? 'client';
        $clientId = isset($data['client_id']) ? (int) $data['client_id'] : null;

        if (!$email || !$password) {
            return $this->json(['error' => 'email та password є обов\'язковими'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Невірний формат email'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!in_array($role, ['client', 'manager', 'admin'], true)) {
            return $this->json(['error' => 'role має бути: client, manager або admin'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email вже зареєстровано'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = new User();
        $user->setEmail($email)
             ->setRole($role)
             ->setClientId($clientId)
             ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => 86400,
            'user'       => $user->toArray(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Не авторизовано'], Response::HTTP_UNAUTHORIZED);
        }
        return $this->json($user->toArray());
    }
}
