<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;

final readonly class Email
{
    public string $value;

    /** @throws ValueObjectException */
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (!filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw new ValueObjectException(\sprintf('Invalid email address: "%s".', $value), translationKey: 'value_object.email.invalid', translationParams: ['{{ value }}' => $value]);
        }

        $this->value = $normalized;
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
