<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;
use Letkode\OrmToolkitBundle\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validEmailProvider(): array
    {
        return [
            'simple' => ['user@example.com', 'user@example.com'],
            'uppercase is lowercased' => ['USER@EXAMPLE.COM', 'user@example.com'],
            'mixed case' => ['John.Doe@Example.ORG', 'john.doe@example.org'],
            'with whitespace' => ['  user@example.com  ', 'user@example.com'],
            'subdomain' => ['user@mail.example.com', 'user@mail.example.com'],
            'plus sign' => ['user+tag@example.com', 'user+tag@example.com'],
        ];
    }

    #[DataProvider('validEmailProvider')]
    public function testConstructsAndNormalizes(string $input, string $expected): void
    {
        $email = new Email($input);

        self::assertSame($expected, $email->value);
        self::assertSame($expected, (string) $email);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'no at sign' => ['notanemail'],
            'no domain' => ['user@'],
            'no local part' => ['@example.com'],
            'double at' => ['user@@example.com'],
            'empty string' => [''],
            'spaces only' => ['   '],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function testThrowsOnInvalidEmail(string $input): void
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage('Invalid email address');
        new Email($input);
    }

    public function testTranslationKeyIsSet(): void
    {
        try {
            new Email('bad');
            self::fail('Expected ValueObjectException');
        } catch (ValueObjectException $e) {
            self::assertSame('value_object.email.invalid', $e->translationKey);
            self::assertArrayHasKey('{{ value }}', $e->translationParams);
        }
    }

    public function testEqualsReturnsTrueForSameEmail(): void
    {
        $a = new Email('user@example.com');
        $b = new Email('USER@Example.COM');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentEmail(): void
    {
        $a = new Email('alice@example.com');
        $b = new Email('bob@example.com');

        self::assertFalse($a->equals($b));
    }
}
