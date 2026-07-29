<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;

final readonly class Phone
{
    public string $value;

    /** @throws ValueObjectException */
    public function __construct(string $value)
    {
        $normalized = preg_replace('/[\s\-().]+/', '', $value);

        if (!preg_match('/^\+?[1-9]\d{6,14}$/', $normalized)) {
            throw new ValueObjectException(\sprintf('Invalid phone number: "%s". Expected E.164-compatible format.', $value), translationKey: 'value_object.phone.invalid', translationParams: ['{{ value }}' => $value]);
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
