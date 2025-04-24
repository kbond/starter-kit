<?php

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

final class ChangeEmailTest extends FunctionalTestCase
{
    use InteractsWithMailer;

    public function testCanChangeEmail(): void
    {
        $user = UserFactory::new()->verified()->create(['email' => 'sconnor@sky.net']);

        $this->assertTrue($user->isVerified());

        $this->browser()
            ->actingAs($user)
            ->visit('/change-email')
            ->assertFieldEquals('Email', 'sconnor@sky.net')
            ->fillField('Email', 'sarah@sky.net')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('You have successfully changed your email.')
            ->assertSee('A verification link has been sent to sarah@sky.net')
            ->assertSee('Your account isn\'t verified. Check your email for a verification link.')
            ->assertAuthenticated(as: 'sarah@sky.net')
        ;

        $this->assertFalse($user->isVerified());

        $this->mailer()
            ->assertSentEmailCount(1)
            ->assertEmailSentTo('sarah@sky.net', function (TestEmail $email) use (&$link) {
                $link = $email->metadata()['link'] ?? self::fail('Link metadata not set');

                $email
                    ->assertSubject('Email Verification')
                    ->assertHasTag('verify-email')
                    ->assertHtmlContains(htmlspecialchars($link))
                    ->assertTextContains($link)
                ;
            })
        ;

        $this->browser()
            ->visit($link)
            ->assertOn('/')
            ->assertSee('Your email has been verified successfully.')
            ->assertAuthenticated(as: 'sarah@sky.net')
            ->assertNotSee('Your account isn\'t verified. Check your email for a verification link.')
        ;

        $this->assertTrue($user->isVerified());
    }

    public function testKeepingTheSameEmailStaysVerified(): void
    {
        $user = UserFactory::new()->verified()->create(['email' => 'sconnor@sky.net']);

        $this->assertTrue($user->isVerified());

        $this->browser()
            ->actingAs($user)
            ->visit('/change-email')
            ->fillField('Email', 'sconnor@SKY.net')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('You have successfully changed your email.')
            ->assertNotSee('Your account isn\'t verified. Check your email for a verification link.')
            ->assertAuthenticated(as: 'sconnor@SKY.net')
        ;

        $this->assertTrue($user->isVerified());
        $this->mailer()->assertNoEmailSent();
    }

    public function testCannotAccessChangeEmailPageIfNotLoggedIn(): void
    {
        $this->browser()
            ->visit('/change-email')
            ->assertOn('/login')
            ->assertNotAuthenticated()
        ;
    }

    /**
     * @dataProvider changeEmailFormValidationProvider
     */
    public function testChangeEmailFormValidation(string $email, string $message): void
    {
        $this->browser()
            ->actingAs(UserFactory::createOne())
            ->visit('/change-email')
            ->fillField('Email', $email)
            ->click('Submit')
            ->assertOn('/change-email')
            ->assertSee($message)
        ;
    }

    public static function changeEmailFormValidationProvider(): iterable
    {
        yield 'required' => [
            'email' => '',
            'message' => 'Please enter your email.',
        ];
        yield 'not an email' => [
            'email' => 'invalid',
            'message' => 'Please enter a valid email address.',
        ];
    }
}
