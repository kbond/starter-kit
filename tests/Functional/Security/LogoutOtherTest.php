<?php

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;

class LogoutOtherTest extends FunctionalTestCase
{
    public function testLogoutOtherDevices(): void
    {
        $user = UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $browser1 = $this->browser()
            ->actingAs($user)
            ->visit('/')
            ->assertAuthenticated(as: $user)
        ;

        $browser2 = $this->browser()
            ->actingAs($user)
            ->visit('/')
            ->assertAuthenticated(as: $user)
        ;

        $browser1
            ->visit('/logout-other')
            ->fillField('Password', '1234')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('Other devices have been logged out.')
            ->assertAuthenticated()
        ;

        $browser2
            ->visit('/')
            ->assertNotAuthenticated()
        ;
    }

    /**
     * @dataProvider logoutOtherValidationProvider
     */
    public function testLogoutOtherValidation(string $password, string $message): void
    {
        $user = UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);
        $hashedPassword = $user->getPassword();

        $this->browser()
            ->actingAs($user)
            ->visit('/logout-other')
            ->fillField('Password', $password)
            ->click('Submit')
            ->assertOn('/logout-other')
            ->assertSee($message)
        ;

        $this->assertSame($hashedPassword, $user->getPassword());
    }

    public function logoutOtherValidationProvider(): iterable
    {
        yield 'blank password' => ['', 'Please enter your password.'];
        yield 'invalid password' => ['invalid', 'Please enter your password.'];
    }
}
