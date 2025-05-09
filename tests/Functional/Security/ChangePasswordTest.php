<?php

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;

class ChangePasswordTest extends FunctionalTestCase
{
    public function testCanChangePassword(): void
    {
        $user = UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);
        $currentPassword = $user->getPassword();

        $this->browser()
            ->actingAs($user)
            ->visit('/change-password')
            ->fillField('Current Password', '1234')
            ->fillField('New Password', 'super s3cure password')
            ->fillField('Repeat Password', 'super s3cure password')
            ->click('Submit')
            ->assertSuccessful()
            ->assertOn('/')
            ->assertSee('You\'ve successfully changed your password.')
            ->assertAuthenticated('mary@example.com')
            ->visit('/logout')
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', 'super s3cure password')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
        ;

        $this->assertNotSame($currentPassword, $user->getPassword());
    }

    public function testCannotAccessChangePasswordPageIfNotLoggedIn(): void
    {
        $this->browser()
            ->visit('/change-password')
            ->assertOn('/login')
            ->assertNotAuthenticated()
        ;
    }

    /**
     * @dataProvider changePasswordFormValidationProvider
     */
    public function testChangePasswordFormValidation(string $current, string $new, string $new2, string $message): void
    {
        $user = UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);
        $currentPassword = $user->getPassword();

        $this->browser()
            ->actingAs($user)
            ->visit('/change-password')
            ->fillField('Current Password', $current)
            ->fillField('New Password', $new)
            ->fillField('Repeat Password', $new2)
            ->click('Submit')
            ->assertOn('/change-password')
            ->assertSee($message)
        ;

        $this->assertSame($currentPassword, $user->getPassword());
    }

    public static function changePasswordFormValidationProvider(): iterable
    {
        yield 'invalid current' => [
            'current' => 'invalid',
            'new' => 'super s3cure password',
            'new2' => 'super s3cure password',
            'message' => 'Please enter your current password',
        ];
        yield 'current required' => [
            'current' => '',
            'new' => 'super s3cure password',
            'new2' => 'super s3cure password',
            'message' => 'Please enter your current password',
        ];
        yield 'new required' => [
            'current' => '1234',
            'new' => '',
            'new2' => '',
            'message' => 'Please enter a password',
        ];
        yield 'new must match' => [
            'current' => '1234',
            'new' => 'super s3cure password',
            'new2' => 'something else',
            'message' => 'The password fields must match',
        ];
    }
}
