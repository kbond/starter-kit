<?php

namespace App\Tests\Functional\User;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;

class RegistrationTest extends FunctionalTestCase
{
    public function testRegistration(): void
    {
        $this->browser()
            ->visit('/register')
            ->fillField('Name', 'Karen Smith')
            ->fillField('Email', 'ksmith@example.com')
            ->fillField('Password', 'super s3cure password')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('Registration successful! You are now logged in.')
            ->assertAuthenticated(as: 'ksmith@example.com')
            ->visit('/logout')
            ->assertNotAuthenticated()
            ->visit('/login')
            ->fillField('Email', 'ksmith@example.com')
            ->fillField('Password', 'super s3cure password')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated(as: 'ksmith@example.com')
        ;

        UserFactory::assert()->exists([
            'name' => 'Karen Smith',
            'email' => 'ksmith@example.com',
        ]);
    }

    /**
     * @dataProvider registrationFormValidationProvider
     */
    public function testRegistrationFormValidation(string $name, string $email, string $password, string $message): void
    {
        $this->browser()
            ->visit('/register')
            ->fillField('Name', $name)
            ->fillField('Email', $email)
            ->fillField('Password', $password)
            ->click('Submit')
            ->assertOn('/register')
            ->assertSee($message)
            ->assertNotAuthenticated()
        ;

        UserFactory::assert()->empty();
    }

    public static function registrationFormValidationProvider(): iterable
    {
        yield 'blank name' => [
            'name' => '',
            'email' => 'kevin@example.com',
            'password' => 'super s3cure password',
            'message' => 'Please enter your name.',
        ];
        yield 'blank email' => [
            'name' => 'Kevin',
            'email' => '',
            'password' => 'super s3cure password',
            'message' => 'Please enter your email',
        ];
        yield 'invalid email' => [
            'name' => 'Kevin',
            'email' => 'invalid',
            'password' => 'super s3cure password',
            'message' => 'Please enter a valid email address',
        ];
        yield 'blank password' => [
            'name' => 'Kevin',
            'email' => 'kevin@example.com',
            'password' => '',
            'message' => 'Please enter a password',
        ];
        yield 'weak password' => [
            'name' => 'Kevin',
            'email' => 'kevin@example.com',
            'password' => '1234',
            'message' => 'The password strength is too low. Please use a stronger password',
        ];
    }
}
