<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Naming;

/**
 * Holds the configured property case for BaseRepositoryTrait, which has no
 * constructor to inject it through. Set once by the bundle on boot.
 */
final class PropertyCaseRegistry
{
    private static PropertyCase $case = PropertyCase::None;

    public static function set(PropertyCase $case): void
    {
        self::$case = $case;
    }

    public static function get(): PropertyCase
    {
        return self::$case;
    }
}
