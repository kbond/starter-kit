<?php

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use App\Tests\FunctionalTestCase;
use Symfony\Component\BrowserKit\CookieJar;

class AuthenticationTest extends FunctionalTestCase
{
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

    public function testRedirectToTargetAfterLogin(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->assertNotAuthenticated()
            ->visit('/login?target=/some/page')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/some/page')
            ->visit('/')
            ->assertAuthenticated('mary@example.com')
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

    public function testLoginWithInvalidCsrf(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->assertNotAuthenticated()
            ->post('/login', ['body' => ['_username' => 'mary@example.com', '_password' => '1234']])
            ->assertOn('/login')
            ->assertSuccessful()
            ->assertSee('Invalid CSRF token.')
            ->assertNotAuthenticated()
        ;
    }

    public function testRememberMeEnabledByDefault(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
            ->use(function (CookieJar $cookieJar) {
                $cookieJar->expire('MOCKSESSID');
            })
            ->withProfiling()
            ->visit('/')
            ->assertAuthenticated('mary@example.com')
        ;
    }

    public function testCanDisableRememberMe(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->uncheckField('Remember me')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
            ->use(function (CookieJar $cookieJar) {
                $cookieJar->expire('MOCKSESSID');
            })
            ->visit('/')
            ->assertNotAuthenticated()
        ;
    }

    public function testChangingPasswordInvalidatesSession(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->uncheckField('Remember me')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
            ->use(function () {
                $user = UserFactory::first();
                $user->setPassword('something-else');
                $user->_save();
            })
            ->withProfiling()
            ->visit('/')
            ->assertNotAuthenticated()
        ;
    }

    public function testChangingPasswordInvalidatesRememberMe(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertSuccessful()
            ->assertAuthenticated('mary@example.com')
            ->use(function (CookieJar $cookieJar) {
                $cookieJar->expire('MOCKSESSID');

                $user = UserFactory::first();
                $user->setPassword('something-else');
                $user->_save();
            })
            ->withProfiling()
            ->visit('/')
            ->assertNotAuthenticated()
        ;
    }

    public function testFullyAuthenticatedLoginRedirect(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated()
            ->visit('/login')
            ->assertOn('/')
            ->assertAuthenticated()
        ;
    }

    public function testFullyAuthenticatedLoginTarget(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated()
            ->visit('/login?target=/some/page')
            ->assertOn('/some/page')
            ->visit('/')
            ->assertAuthenticated()
        ;
    }

    public function testCanFullyAuthenticateIfOnlyRemembered(): void
    {
        UserFactory::createOne(['email' => 'mary@example.com', 'password' => '1234']);

        $this->browser()
            ->visit('/login')
            ->fillField('Email', 'mary@example.com')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated('mary@example.com')
            ->use(function (CookieJar $cookieJar) {
                $cookieJar->expire('MOCKSESSID');
            })
            ->visit('/login')
            ->assertOn('/login')
            ->fillField('Password', '1234')
            ->click('Sign in')
            ->assertOn('/')
            ->assertAuthenticated('mary@example.com')
        ;
    }

    public function testAutoRedirectedToAuthenticatedResourceAfterLogin(): void
    {
        // complete this test when you have a page that requires authentication
        $this->markTestIncomplete();
    }

    public function testAutoRedirectedToFullyAuthenticatedResourceAfterFullyAuthenticated(): void
    {
        // complete this test when/if you have a page that requires the user be "fully authenticated"
        $this->markTestIncomplete();
    }
}
