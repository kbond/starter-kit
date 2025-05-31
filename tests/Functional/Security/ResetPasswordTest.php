<?php

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Browser;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

class ResetPasswordTest extends FunctionalTestCase
{
    use InteractsWithMailer;
    use ClockSensitiveTrait;

    public function testUserCanResetPassword(): void
    {
        UserFactory::createOne(['email' => 'jane@example.com', 'password' => '1234']);

        $this->browser()
            ->assertNotAuthenticated()
            ->visit('/forgot-password')
            ->fillField('Email', 'jane@example.com')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('A password reset link has been sent to jane@example.com.')
        ;

        $this->mailer()
            ->assertSentEmailCount(1)
            ->assertEmailSentTo('jane@example.com', function (TestEmail $email) use (&$link) {
                $link = $email->metadata()['link'] ?? self::fail('Link metadata not set');

                $email
                    ->assertSubject('Password Reset Request')
                    ->assertHasTag('forgot-password')
                    ->assertHtmlContains(htmlspecialchars($link))
                    ->assertTextContains($link)
                ;
            })
        ;

        $this->browser()
            ->visit($link)
            ->assertOn('/reset-password', ['path'])
            ->fillField('New Password', 'super s3cure password')
            ->fillField('Repeat Password', 'super s3cure password')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('Your password has been reset successfully, you are now logged in.')
            ->assertAuthenticated(as: 'jane@example.com')
            ->visit('/logout')
            ->assertNotAuthenticated()
            ->visit('/login')
            ->fillField('Email', 'jane@example.com')
            ->fillField('Password', 'super s3cure password')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated(as: 'jane@example.com')
        ;
    }

    public function testNonExistentAccountsArentLeaked(): void
    {
        $this->browser()
            ->visit('/forgot-password')
            ->fillField('Email', 'unknown@example.com')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('A password reset link has been sent to unknown@example.com.')
        ;

        $this->mailer()->assertNoEmailSent();
    }

    /**
     * @dataProvider forgotPasswordValidationProvider
     */
    public function testForgotPasswordValidation(string $email, string $message): void
    {
        $this->browser()
            ->visit('/forgot-password')
            ->fillField('Email', $email)
            ->click('Submit')
            ->assertOn('/forgot-password')
            ->assertSee($message)
        ;

        $this->mailer()->assertNoEmailSent();
    }

    public static function forgotPasswordValidationProvider(): iterable
    {
        yield 'blank email' => ['', 'Please enter your email'];
        yield 'invalid email' => ['not-an-email', 'Please enter a valid email address'];
    }

    /**
     * @dataProvider resetPasswordValidationProvider
     */
    public function testResetPasswordValidation(string $password1, string $password2, string $message): void
    {
        $user = UserFactory::createOne(['email' => 'john@example.com']);
        $password = $user->getPassword();

        $this->browser()
            ->visit($this->createValidResetLink('john@example.com'))
            ->fillField('New Password', $password1)
            ->fillField('Repeat Password', $password2)
            ->click('Submit')
            ->assertSee($message)
            ->assertOn('/reset-password', ['path'])
        ;

        $this->assertSame($password, $user->getPassword());
    }

    public function resetPasswordValidationProvider(): iterable
    {
        yield 'blank password' => ['', '', 'Please enter a password'];
        yield 'mismatched password' => ['1234', '4321', 'The password fields must match'];
        yield 'weak password' => ['1234', '1234', 'The password strength is too low. Please use a stronger password.'];
    }

    public function testInvalidResetLink(): void
    {
        $user = UserFactory::createOne(['email' => 'john@example.com']);

        $this->browser()
            ->visit('/reset-password')
            ->assertStatus(404)
            ->visit("/reset-password/{$user->getId()}")
            ->assertOn('/forgot-password')
            ->assertSee('This reset password link is invalid, please try again')
            ->visit('/reset-password/1234')
            ->assertOn('/forgot-password')
            ->assertSee('This reset password link is invalid, please try again')
            ->visit("/reset-password/{$user->getId()}?_hash=invalid-hash")
            ->assertOn('/forgot-password')
            ->assertSee('This reset password link is invalid, please try again')
            ->use(function (Browser $browser) {
                // Create a valid reset link
                $link = $this->createValidResetLink('john@example.com');

                // delete users
                UserFactory::truncate();

                // visit the link
                $browser->visit($link);
            })
            ->assertStatus(404)
        ;
    }

    public function testExpiredResetLink(): void
    {
        UserFactory::createOne(['email' => 'john@example.com']);

        $link = $this->createValidResetLink('john@example.com');

        self::mockTime('+2 hours');

        $this->browser()
            ->visit($link)
            ->assertOn('/forgot-password')
            ->assertSee('This reset password link has expired, please try again')
        ;
    }

    public function testAlreadyUsedResetLink(): void
    {
        UserFactory::createOne(['email' => 'john@example.com']);

        $link = $this->createValidResetLink('john@example.com');

        $this->browser()
            ->visit($link)
            ->fillField('New Password', 'super s3cure password')
            ->fillField('Repeat Password', 'super s3cure password')
            ->click('Submit')
            ->assertOn('/')
            ->assertSee('Your password has been reset successfully, you are now logged in.')
            ->visit($link)
            ->assertOn('/forgot-password')
            ->assertSee('This password reset link has already been used.')
        ;
    }

    private function createValidResetLink(string $email): string
    {
        $this->browser()
            ->visit('/forgot-password')
            ->fillField('Email', $email)
            ->click('Submit')
        ;

        return $this->mailer()
            ->sentEmails()
            ->whereTo($email)
            ->first()
            ->metadata()['link'] ?? self::fail('Link metadata not set')
        ;
    }
}
