<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixture extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admins = [
            [
                'email' => 'admin@growfico.com',
                'roles' => ['ROLE_ADMIN'],
                'password' => 'AdminGrowfico@2025',
                'firstName' => 'Admin',
                'lastName' => 'User',
                'phone' => '09123456789',
            ],
        ];

        foreach ($admins as $data) {
            // Check if user already exists to avoid duplicates
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setPhone($data['phone']);
            $user->setRoles($data['roles']);
            $user->setIsActive(true);

            $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashed);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
