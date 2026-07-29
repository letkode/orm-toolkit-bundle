<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;
use Letkode\OrmToolkitBundle\ValueObject\Username;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validUsernameProvider(): array
    {
        return [
            'simple letters' => ['alice', 'alice'],
            'with numbers' => ['user123', 'user123'],
            'with underscore' => ['my_user', 'my_user'],
            'with dot' => ['john.doe', 'john.doe'],
            'with hyphen' => ['john-doe', 'john-doe'],
            'with leading whitespace trimmed' => ['  bob  ', 'bob'],
            'uppercase letters' => ['AdminUser', 'AdminUser'],
            'minimum 3 chars' => ['abc', 'abc'],
            'exactly 50 chars' => [str_repeat('a', 50), str_repeat('a', 50)],
        ];
    }

    #[DataProvider('validUsernameProvider')]
    public function testConstructsAndNormalizes(string $input, string $expected): void
    {
        $username = new Username($input);

        self::assertSame($expected, $username->value);
        self::assertSame($expected, (string) $username);
    }

    public function testThrowsWhenTooShort(): void
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage('3 and 50');
        new Username('ab');
    }

    public function testThrowsWhenTooLong(): void
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage('3 and 50');
        new Username(str_repeat('a', 51));
    }

    public function testThrowsOnInvalidCharacters(): void
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage('letters, numbers, underscores');
        new Username('hello world');
    }

    public function testThrowsOnAtSign(): void
    {
        $this->expectException(ValueObjectException::class);
        new Username('user@name');
    }

    public function testTranslationKeyForLengthViolation(): void
    {
        try {
            new Username('ab');
            self::fail('Expected ValueObjectException');
        } catch (ValueObjectException $e) {
            self::assertSame('value_object.username.length', $e->translationKey);
            self::assertArrayHasKey('{{ min }}', $e->translationParams);
            self::assertArrayHasKey('{{ max }}', $e->translationParams);
        }
    }

    public function testTranslationKeyForInvalidChars(): void
    {
        try {
            new Username('bad user');
            self::fail('Expected ValueObjectException');
        } catch (ValueObjectException $e) {
            self::assertSame('value_object.username.invalid_chars', $e->translationKey);
        }
    }

    public function testEqualsReturnsTrueForSameUsername(): void
    {
        $a = new Username('alice');
        $b = new Username('  alice  ');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentUsername(): void
    {
        $a = new Username('alice');
        $b = new Username('bob');

        self::assertFalse($a->equals($b));
    }
}
