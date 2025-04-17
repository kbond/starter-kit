<?php

namespace App\Tests\Functional;

use App\Tests\FunctionalTestCase;

class HomepageTest extends FunctionalTestCase
{
    public function testHomepage(): void
    {
        $this->browser()
            ->visit('/')
            ->assertSuccessful()
        ;
    }
}
