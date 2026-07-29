<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;

final readonly class Username
{
    public string $value;

    /** @throws ValueObjectException */
    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (\strlen($trimmed) < 3 || \strlen($trimmed) > 50) {
            throw new ValueObjectException('Username must be between 3 and 50 characters.', translationKey: 'value_object.username.length', translationParams: ['{{ min }}' => 3, '{{ max }}' => 50]);
        }

        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $trimmed)) {
            throw new ValueObjectException('Username may only contain letters, numbers, underscores, dots, and hyphens.', translationKey: 'value_object.username.invalid_chars');
        }

        $this->value = $trimmed;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
