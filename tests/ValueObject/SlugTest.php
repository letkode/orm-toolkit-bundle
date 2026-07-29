<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\ValueObject;

use Letkode\CommonBundle\Exception\ValueObjectException;
use Letkode\OrmToolkitBundle\ValueObject\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function validSlugProvider(): array
    {
        return [
            'simple words' => ['Hello World', 'hello-world'],
            'underscores to hyphens' => ['my_product', 'my-product'],
            'consecutive hyphens collapsed' => ['foo--bar', 'foo-bar'],
            'leading and trailing spaces' => ['  acme-corp  ', 'acme-corp'],
            'mixed case' => ['My Product Name', 'my-product-name'],
            'numbers' => ['php-84', 'php-84'],
            'single char' => ['a', 'a'],
            'already valid' => ['valid-slug', 'valid-slug'],
        ];
    }

    #[DataProvider('validSlugProvider')]
    public function testConstructsAndNormalizes(string $input, string $expected): void
    {
        $slug = new Slug($input);

        self::assertSame($expected, $slug->value);
        self::assertSame($expected, (string) $slug);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSlugProvider(): array
    {
        return [
            'empty string' => [''],
            'only hyphens after strip' => ['---'],
            'only special chars' => ['!!!@@@'],
            'spaces only' => ['   '],
        ];
    }

    #[DataProvider('invalidSlugProvider')]
    public function testThrowsOnInvalidSlug(string $input): void
    {
        $this->expectException(ValueObjectException::class);
        new Slug($input);
    }

    public function testTranslationKeyIsSet(): void
    {
        try {
            new Slug('!!!');
            self::fail('Expected ValueObjectException');
        } catch (ValueObjectException $e) {
            self::assertSame('value_object.slug.invalid', $e->translationKey);
        }
    }

    public function testEqualsReturnsTrueForSameSlug(): void
    {
        $a = new Slug('Hello World');
        $b = new Slug('hello-world');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentSlug(): void
    {
        $a = new Slug('foo');
        $b = new Slug('bar');

        self::assertFalse($a->equals($b));
    }

    public function testSpecialCharsAreStripped(): void
    {
        $slug = new Slug('hello@world!');

        self::assertSame('helloworld', $slug->value);
    }
}
