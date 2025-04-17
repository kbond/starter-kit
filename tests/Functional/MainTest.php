<?php

namespace App\Tests\Functional;

use App\Tests\FunctionalTestCase;

class MainTest extends FunctionalTestCase
{
    public function testHomepage(): void
    {
        $this->browser()
            ->visit('/')
            ->assertSuccessful()
        ;
    }
}
