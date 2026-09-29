<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Exception;

use Letkode\OrmToolkitBundle\Exception\Validation\ValueObjectException;
use PHPUnit\Framework\TestCase;

final class ValueObjectExceptionTest extends TestCase
{
    public function testIsNotFinalSoItCanBeExtended(): void
    {
        self::assertFalse(new \ReflectionClass(ValueObjectException::class)->isFinal());
    }

    public function testCarriesTheTranslationKeyAndParams(): void
    {
        $exception = new ValueObjectException('Invalid email.', 'value_object.email.invalid', ['{{ value }}' => 'bad@']);

        self::assertSame('Invalid email.', $exception->getMessage());
        self::assertSame('value_object.email.invalid', $exception->translationKey);
        self::assertSame(['{{ value }}' => 'bad@'], $exception->translationParams);
    }
}
