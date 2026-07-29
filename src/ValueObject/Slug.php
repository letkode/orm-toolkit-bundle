<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;

final readonly class Slug
{
    public string $value;

    /** @throws ValueObjectException */
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s_]+/', '-', $normalized);
        $normalized = preg_replace('/[^a-z0-9\-]/', '', $normalized);
        $normalized = preg_replace('/-{2,}/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,253}[a-z0-9]$|^[a-z0-9]$/', $normalized)) {
            throw new ValueObjectException(\sprintf('Invalid slug: "%s". Must be 2–255 characters, only lowercase letters, digits, and hyphens.', $value), translationKey: 'value_object.slug.invalid', translationParams: ['{{ value }}' => $value]);
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
