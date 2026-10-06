<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Naming;

use Symfony\Component\String\UnicodeString;

/**
 * How entity properties are spelled, used to translate the field names a client
 * sends (`legal_name`, `legalName`, `legal-name`) into Doctrine property names.
 *
 * The converters accept any input spelling, so only the target needs declaring.
 */
enum PropertyCase: string
{
    case None = 'none';
    case Camel = 'camel';
    case Snake = 'snake';

    public function convert(string $field): string
    {
        return match ($this) {
            self::None => $field,
            self::Camel => new UnicodeString($field)->camel()->toString(),
            self::Snake => new UnicodeString($field)->snake()->toString(),
        };
    }
}
