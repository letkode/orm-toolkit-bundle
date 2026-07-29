<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;
use Letkode\OrmToolkitBundle\ValueObject\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'E.164 compact' => ['+56912345678', '+56912345678'],
            'with spaces' => ['+56 9 1234 5678', '+56912345678'],
            'with dashes' => ['+56-9-1234-5678', '+56912345678'],
            'with parentheses' => ['+1(800)5551234', '+18005551234'],
            'no plus sign' => ['56912345678', '56912345678'],
            'mixed separators' => ['+1 (800) 555-1234', '+18005551234'],
        ];
    }

    #[DataProvider('validPhoneProvider')]
    public function testConstructsAndNormalizes(string $input, string $expected): void
    {
        $phone = new Phone($input);

        self::assertSame($expected, $phone->value);
        self::assertSame($expected, (string) $phone);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too short' => ['+123'],
            'starts with zero' => ['+0123456789'],
            'letters' => ['abc1234567'],
            'empty' => [''],
            'only spaces' => ['   '],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function testThrowsOnInvalidPhone(string $input): void
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage('Invalid phone number');
        new Phone($input);
    }

    public function testTranslationKeyIsSet(): void
    {
        try {
            new Phone('bad');
            self::fail('Expected ValueObjectException');
        } catch (ValueObjectException $e) {
            self::assertSame('value_object.phone.invalid', $e->translationKey);
        }
    }

    public function testEqualsReturnsTrueForSameNumber(): void
    {
        $a = new Phone('+56 9 1234 5678');
        $b = new Phone('+56912345678');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentNumber(): void
    {
        $a = new Phone('+56912345678');
        $b = new Phone('+56912345679');

        self::assertFalse($a->equals($b));
    }
}
