<?php

namespace App\Tests\Functional\User;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Browser;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

class RegistrationTest extends FunctionalTestCase
{
    use InteractsWithMailer;
    use ClockSensitiveTrait;

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
            ->assertSee('A verification link has been sent to ksmith@example.com')
            ->assertSee('Your account isn\'t verified. Check your email for a verification link.')
            ->assertAuthenticated(as: 'ksmith@example.com')
        ;

        $this->mailer()
            ->assertSentEmailCount(1)
            ->assertEmailSentTo('ksmith@example.com', function (TestEmail $email) use (&$link) {
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
            ->visit('/logout')
            ->assertNotAuthenticated()
            ->visit('/login')
            ->fillField('Email', 'ksmith@example.com')
            ->fillField('Password', 'super s3cure password')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated(as: 'ksmith@example.com')
            ->assertSee('Your account isn\'t verified. Check your email for a verification link.')
        ;

        $user = UserFactory::find(['email' => 'ksmith@example.com']);

        $this->assertSame('Karen Smith', $user->getName());
        $this->assertFalse($user->isVerified());

        $this->browser()
            ->visit($link)
            ->assertOn('/')
            ->assertSee('Your email has been verified successfully.')
            ->assertAuthenticated(as: 'ksmith@example.com')
            ->assertNotSee('Your account isn\'t verified. Check your email for a verification link.')
        ;

        $this->assertTrue($user->isVerified());
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

    public function testCanResendVerificationEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'jane@example.com']);

        $this->assertFalse($user->isVerified());

        $this->browser()
            ->actingAs($user)
            ->visit('/')
            ->assertSee('Your account isn\'t verified. Check your email for a verification link. Resend Email')
            ->post('/send-verification')
            ->assertOn('/')
            ->assertSee('A verification link has been sent to jane@example.com')
        ;

        $this->mailer()
            ->assertSentEmailCount(1)
            ->assertEmailSentTo('jane@example.com', function (TestEmail $email) use (&$link) {
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
            ->assertAuthenticated(as: 'jane@example.com')
            ->assertNotSee('Your account isn\'t verified. Check your email for a verification link.')
        ;

        $this->assertTrue($user->isVerified());
    }

    public function testExpiredVerificationLink(): void
    {
        $user = UserFactory::createOne();
        $link = $this->createValidVerificationLink($user);

        self::mockTime('+2 days');

        $this->browser()
            ->visit($link)
            ->assertOn('/')
            ->assertSee('The verification link is invalid or has expired, try resending.')
        ;
    }

    public function testInvalidVerificationLink(): void
    {
        $user = UserFactory::createOne();

        $this->browser()
            ->visit('/verify-email')
            ->assertStatus(404)
            ->visit("/verify-email/{$user->getId()}")
            ->assertOn('/')
            ->assertSee('The verification link is invalid or has expired, try resending.')
            ->visit('/verify-email/1234')
            ->assertOn('/')
            ->assertSee('The verification link is invalid or has expired, try resending.')
            ->visit("/verify-email/{$user->getId()}?_hash=invalid")
            ->assertOn('/')
            ->assertSee('The verification link is invalid or has expired, try resending.')
            ->use(function (Browser $browser) use ($user) {
                $link = $this->createValidVerificationLink($user);

                // delete users
                UserFactory::truncate();

                // visit the link
                $browser->visit($link);
            })
            ->assertStatus(404)
        ;
    }

    public function testAlreadyUsedVerificationLink(): void
    {
        $user = UserFactory::createOne();
        $link = $this->createValidVerificationLink($user);

        $user->markVerified();
        $user->_save();

        $this->browser()
            ->visit($link)
            ->assertOn('/')
            ->assertSee('This verification link has already been used.')
        ;
    }

    private function createValidVerificationLink(User $user): string
    {
        $this->browser()
            ->actingAs($user)
            ->post('/send-verification')
        ;

        return $this->mailer()
            ->sentEmails()
            ->whereTo($user->getEmail())
            ->first()
            ->metadata()['link'] ?? self::fail('Link metadata not set')
        ;
    }
}
