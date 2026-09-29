<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Trait\Repository;

use Letkode\HttpExceptionBundle\Contract\HttpStatusExceptionInterface;
use Letkode\HttpExceptionBundle\Exception\EntityNotFoundException;
use Letkode\OrmToolkitBundle\Trait\Repository\BaseRepositoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FindOrFailTestRepository
{
    /** @use BaseRepositoryTrait<object> */
    use BaseRepositoryTrait;

    /** @var list<array<string, mixed>> */
    public array $criteria = [];

    public function __construct(private readonly object|null $entity)
    {
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): object|null
    {
        $this->criteria[] = $criteria;

        return $this->entity;
    }
}

final class BaseRepositoryTraitFindOrFailTest extends TestCase
{
    public function testFindByUuidQueriesByTheUuidField(): void
    {
        $uuid = Uuid::v7();
        $entity = new \stdClass();
        $repository = new FindOrFailTestRepository($entity);

        self::assertSame($entity, $repository->findByUuid($uuid));
        self::assertSame([['uuid' => $uuid]], $repository->criteria);
    }

    public function testFindOrFailByUuidReturnsTheEntity(): void
    {
        $entity = new \stdClass();

        self::assertSame($entity, new FindOrFailTestRepository($entity)->findOrFailByUuid(Uuid::v7()));
    }

    public function testFindOrFailByUuidThrowsTheSharedNotFoundExceptionWhenMissing(): void
    {
        try {
            new FindOrFailTestRepository(null)->findOrFailByUuid(Uuid::v7());
            self::fail('Expected an EntityNotFoundException.');
        } catch (EntityNotFoundException $exception) {
            self::assertInstanceOf(HttpStatusExceptionInterface::class, $exception);
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame('ENTITY_NOT_FOUND', $exception->getErrorCode());
            self::assertSame('Not found.', $exception->getMessage());
        }
    }

    public function testFindOrFailByUuidUsesTheGivenMessage(): void
    {
        try {
            new FindOrFailTestRepository(null)->findOrFailByUuid(Uuid::v7(), 'User not found.');
            self::fail('Expected an EntityNotFoundException.');
        } catch (EntityNotFoundException $exception) {
            self::assertSame('User not found.', $exception->getMessage());
        }
    }
}
