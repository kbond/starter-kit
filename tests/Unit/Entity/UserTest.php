<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetFirstName(): void
    {
        $this->assertSame('John', (new User())->setName('John Doe')->getFirstName());
        $this->assertSame('John', (new User())->setName('John')->getFirstName());
    }

    public function testGetAvatarUrl(): void
    {
        $user = new User();
        $user->setName('John Smith');
        $user->setEmail('john@example.com');

        $this->assertSame(
            'https://www.gravatar.com/avatar/d4c74594d841139328695756648b6bd6?s=300&d=https%3A%2F%2Fui-avatars.com%2Fapi%2FJohn%2BSmith%2F300%2Frandom%2F8b5d5d%2F1%2F0.85%2Ffalse%2Ftrue%2Ftrue',
            $user->getAvatarUrl(),
        );
    }
}
