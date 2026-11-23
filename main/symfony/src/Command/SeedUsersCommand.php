<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-users', description: 'Seeds 3 test users (admin, manager, client)')]
class SeedUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = [
            ['email' => 'admin@bank.com',   'password' => 'admin123',   'role' => 'admin',   'clientId' => null],
            ['email' => 'manager@bank.com', 'password' => 'manager123', 'role' => 'manager', 'clientId' => null],
            ['email' => 'client@bank.com',  'password' => 'client123',  'role' => 'client',  'clientId' => 1],
        ];

        foreach ($users as $data) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existing) {
                $io->note("Користувач {$data['email']} вже існує — пропускаємо.");
                continue;
            }

            $user = new User();
            $user->setEmail($data['email'])
                 ->setRole($data['role'])
                 ->setClientId($data['clientId'])
                 ->setPassword($this->hasher->hashPassword($user, $data['password']));

            $this->em->persist($user);
            $io->success("Створено [{$data['role']}]: {$data['email']} / {$data['password']}");
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
