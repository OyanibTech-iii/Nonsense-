<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $accounts = [
            [
                'email' => 'useraccount@gmail.com',
                'roles' => [],
                'password' => 'User@1234',
                'firstName' => 'Normal',
                'lastName' => 'User',
                'phone' => '09234567891',
            ],
            [
                'email' => 'staffaccount@gmail.com',
                'roles' => ['ROLE_STAFF'],
                'password' => 'Staff@1234',
                'firstName' => 'Staff',
                'lastName' => 'Member',
                'phone' => '0987654321',
            ],
            [
                'email' => 'pacificooyanib@gmail.com',
                'roles' => ['ROLE_ADMIN'],
                'password' => 'Admin@1234',
                'firstName' => 'Admin',
                'lastName' => 'Account',
                'phone' => '0912345678',
            ],
        ];

        foreach ($accounts as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setPhone($data['phone']);
            $user->setRoles($data['roles']);

            $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashed);

            $manager->persist($user);
        }

        $manager->flush();
    }

}

