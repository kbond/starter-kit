<?php

namespace App\Tests\Functional\User;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;

class ProfileTest extends FunctionalTestCase
{
    public function testCanUpdateProfile(): void
    {
        $user = UserFactory::createOne(['name' => 'Mary Edwards']);

        $this->browser()
            ->actingAs($user)
            ->visit('/profile')
            ->assertFieldEquals('Name', 'Mary Edwards')
            ->fillField('Name', 'John Smith')
            ->click('Submit')
            ->assertOn('/profile')
            ->assertSuccessful()
            ->assertSee('You\'ve successfully updated your profile.')
            ->assertFieldEquals('Name', 'John Smith')
        ;

        $this->assertSame('John Smith', $user->getName());
    }

    public function testCannotAccessProfilePageIfNotLoggedIn(): void
    {
        $this->browser()
            ->visit('/profile')
            ->assertOn('/login')
            ->assertNotAuthenticated()
        ;
    }

    /**
     * @dataProvider userProfileFormValidationProvider
     */
    public function testUserProfileFormValidation(string $name, string $message): void
    {
        $user = UserFactory::createOne(['name' => 'Mary Edwards']);

        $this->browser()
            ->actingAs($user)
            ->visit('/profile')
            ->fillField('Name', $name)
            ->click('Submit')
            ->assertOn('/profile')
            ->assertSee($message)
        ;

        $this->assertSame('Mary Edwards', $user->getName());
    }

    public static function userProfileFormValidationProvider(): iterable
    {
        yield 'empty name' => [
            'name' => '',
            'message' => 'Please enter your name.',
        ];
    }
}
