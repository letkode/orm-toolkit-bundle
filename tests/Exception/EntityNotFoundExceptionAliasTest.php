<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Exception;

use Letkode\HttpExceptionBundle\Contract\HttpStatusExceptionInterface;
use Letkode\HttpExceptionBundle\Exception\EntityNotFoundException;
use Letkode\OrmToolkitBundle\Exception\Http\EntityNotFoundException as LegacyEntityNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * The bundle's own EntityNotFoundException was replaced by the shared HTTP one; the old class name stays
 * as an alias so existing `catch` blocks and `new` calls keep working.
 */
final class EntityNotFoundExceptionAliasTest extends TestCase
{
    public function testTheLegacyNameIsAnAliasOfTheSharedException(): void
    {
        $exception = new LegacyEntityNotFoundException('User not found.');

        self::assertInstanceOf(EntityNotFoundException::class, $exception);
        self::assertInstanceOf(HttpStatusExceptionInterface::class, $exception);
        self::assertSame(EntityNotFoundException::class, $exception::class);
    }

    public function testItKeepsTheOriginalStatusAndErrorCodeBehavior(): void
    {
        $exception = new LegacyEntityNotFoundException('User not found.');

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('ENTITY_NOT_FOUND', $exception->getErrorCode());
        self::assertSame('CUSTOM', new LegacyEntityNotFoundException('x', 'CUSTOM')->getErrorCode());
    }

    public function testACatchOfTheLegacyNameStillCatchesTheSharedException(): void
    {
        try {
            throw new EntityNotFoundException('gone');
        } catch (LegacyEntityNotFoundException $caught) {
            self::assertSame('gone', $caught->getMessage());

            return;
        }
    }
}
