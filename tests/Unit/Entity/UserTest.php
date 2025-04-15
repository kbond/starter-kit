<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testFirstName(): void
    {
        $this->assertSame('John', (new User())->setName('John Doe')->getFirstName());
        $this->assertSame('John', (new User())->setName('John')->getFirstName());
    }
}
