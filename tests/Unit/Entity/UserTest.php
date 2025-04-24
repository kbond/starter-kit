<?php

namespace App\Tests\Unit\Entity;

use App\Factory\UserFactory;
use App\Tests\UnitTestCase;

class UserTest extends UnitTestCase
{
    public function testGetFirstName(): void
    {
        $this->assertSame('John', UserFactory::createOne(['name' => 'John Doe'])->getFirstName());
        $this->assertSame('John', UserFactory::createOne(['name' => 'John'])->getFirstName());
    }

    public function testGetAvatarUrl(): void
    {
        $user = UserFactory::createOne([
            'name' => 'John Smith',
            'email' => 'john@example.com',
        ]);

        $this->assertSame(
            'https://www.gravatar.com/avatar/d4c74594d841139328695756648b6bd6?s=300&d=https%3A%2F%2Fui-avatars.com%2Fapi%2FJohn%2BSmith%2F300%2Frandom%2F8b5d5d%2F1%2F0.85%2Ffalse%2Ftrue%2Ftrue',
            $user->getAvatarUrl(),
        );
    }

    public function testVerificationRole(): void
    {
        $user = UserFactory::createOne();

        $this->assertFalse($user->isVerified());
        $this->assertSame(['ROLE_USER'], $user->getRoles());

        $user->markVerified();

        $this->assertSame(['ROLE_USER', 'ROLE_VERIFIED'], $user->getRoles());
        $this->assertTrue($user->isVerified());
    }

    public function testChangeEmail(): void
    {
        $user = UserFactory::new(['email' => 'john@example.com'])->verified()->create();

        $this->assertTrue($user->isVerified());

        $user->changeEmail('John@example.com');

        $this->assertTrue($user->isVerified());

        $user->changeEmail('john.smith@example.com');

        $this->assertFalse($user->isVerified());
    }
}
