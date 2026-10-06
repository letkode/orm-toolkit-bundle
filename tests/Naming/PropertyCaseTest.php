<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Naming;

use Letkode\OrmToolkitBundle\Naming\PropertyCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PropertyCaseTest extends TestCase
{
    /**
     * @return iterable<string, array{PropertyCase, string, string}>
     */
    public static function conversions(): iterable
    {
        yield 'none keeps snake' => [PropertyCase::None, 'legal_name', 'legal_name'];
        yield 'camel from snake' => [PropertyCase::Camel, 'legal_name', 'legalName'];
        yield 'camel from kebab' => [PropertyCase::Camel, 'legal-name', 'legalName'];
        yield 'camel is idempotent' => [PropertyCase::Camel, 'legalName', 'legalName'];
        yield 'camel single word' => [PropertyCase::Camel, 'name', 'name'];
        yield 'snake from camel' => [PropertyCase::Snake, 'legalName', 'legal_name'];
        yield 'snake is idempotent' => [PropertyCase::Snake, 'legal_name', 'legal_name'];
    }

    #[DataProvider('conversions')]
    public function testConvert(PropertyCase $case, string $input, string $expected): void
    {
        self::assertSame($expected, $case->convert($input));
    }
}
