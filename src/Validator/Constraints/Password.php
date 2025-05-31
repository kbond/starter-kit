<?php

namespace App\Validator\Constraints;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

final class Password extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new NotBlank(
                message: 'Please enter a password',
            ),
            new Length(
                min: 12,
                // max length allowed by Symfony for security reasons
                max: PasswordHasherInterface::MAX_PASSWORD_LENGTH,
                minMessage: 'Your password should be at least {{ limit }} characters',
            ),
            new PasswordStrength(),
            new NotCompromisedPassword(),
        ];
    }
}
