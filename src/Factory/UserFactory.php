<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct(
        private ?UserPasswordHasherInterface $passwordHasher = null,
    ) {
    }

    public static function class(): string
    {
        return User::class;
    }

    public function admin(): static
    {
        return $this->beforeInstantiate(function (array $parameters): array {
            $parameters['roles'][] = 'ROLE_ADMIN';

            return $parameters;
        });
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->text(180),
            'password' => 'password',
            'roles' => [],
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function(User $user): void {
                if ($this->passwordHasher) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
                }
            })
        ;
    }
}
