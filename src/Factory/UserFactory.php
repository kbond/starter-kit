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
    public function __construct(
        private ?UserPasswordHasherInterface $passwordHasher = null,
    ) {
    }

    public static function class(): string
    {
        return User::class;
    }

    public function admin(): self
    {
        return $this->withRole('ROLE_ADMIN');
    }

    public function withRole(string $value): self
    {
        return $this->beforeInstantiate(function (array $parameters) use ($value): array {
            $parameters['roles'][] = $value;

            return $parameters;
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => sprintf('%s %s', self::faker()->firstName(), self::faker()->lastName()),
            'email' => self::faker()->email(),
            'password' => 'password',
            'roles' => [],
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (User $user): void {
                if ($this->passwordHasher) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
                }
            })
        ;
    }
}
