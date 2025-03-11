<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Browser\Test\HasBrowser;

class MainTest extends KernelTestCase
{
    use HasBrowser;

    public function testHomepage(): void
    {
        $this->browser()
            ->visit('/')
            ->assertSuccessful()
        ;
    }
}
