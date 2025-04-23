<?php

use Symfony\Component\Dotenv\Dotenv;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Test\UnitTestConfig;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

UnitTestConfig::configure(
    instantiator: Instantiator::withoutConstructor()->alwaysForce(),
);
