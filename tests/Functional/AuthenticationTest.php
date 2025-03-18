<?php

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthenticationTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;
    use HasBrowser;

    public function testCanLoginAndLogout(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->assertNotAuthenticated()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
            ->visit('/logout')
            ->assertOn('/')
            ->assertNotAuthenticated()
        ;
    }

    public function testLoginWithInvalidPassword(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', 'invalid')
            ->click('Sign in')
            ->assertOn('/login')
            ->assertSuccessful()
            ->assertFieldEquals('Email', 'mary@example.com')
            ->assertSee('Invalid credentials.')
            ->assertNotAuthenticated()
        ;
    }

    public function testLoginWithInvalidEmail(): void
    {
        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'invalid@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/login')
            ->assertSuccessful()
            ->assertFieldEquals('Email', 'invalid@example.com')
            ->assertSee('Invalid credentials.')
            ->assertNotAuthenticated()
        ;
    }
}
