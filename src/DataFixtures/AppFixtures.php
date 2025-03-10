<?php

namespace App\DataFixtures;

use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // standard user
        UserFactory::new()->create([
            'email' => 'dad@example.com',
            'password' => 'password',
        ]);

        // admin user
        UserFactory::new()->admin()->create([
            'email' => 'mom@example.com',
            'password' => 'password',
        ]);
    }
}
