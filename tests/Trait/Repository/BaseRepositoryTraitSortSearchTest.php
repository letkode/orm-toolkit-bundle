<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Trait\Repository;

use Doctrine\ORM\QueryBuilder;
use Letkode\ListQueryBundle\Naming\PropertyCase;
use Letkode\ListQueryBundle\Naming\PropertyCaseRegistry;
use Letkode\ListQueryBundle\Sort\SortInput;
use Letkode\OrmToolkitBundle\Trait\Repository\BaseRepositoryTrait;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the private applySearch()/applySort() methods for testing without touching visibility in the trait.
 */
final class SortSearchTestRepository
{
    /** @use BaseRepositoryTrait<object> */
    use BaseRepositoryTrait;

    /** @param string[] $searchable */
    public function applySearchPublic(QueryBuilder $qb, string $alias, string|null $q, array $searchable, int $minSearchLength = 3): void
    {
        $this->applySearch($qb, $alias, $q, $searchable, $minSearchLength);
    }

    /** @param array<int|string, mixed> $sortable */
    public function applySortPublic(QueryBuilder $qb, string $alias, string|null $sort, string $dir, array $sortable): void
    {
        // @phpstan-ignore argument.type (the test passes invalid entries on purpose)
        $this->applySort($qb, $alias, $sort, $dir, $sortable);
    }
}

final class BaseRepositoryTraitSortSearchTest extends TestCase
{
    private SortSearchTestRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new SortSearchTestRepository();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $params Populated on setParameter() calls
     * @param list<string>         $wheres Populated on andWhere() calls (cast to string)
     */
    private function createSearchQbMock(array &$params, array &$wheres): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('setParameter')
            ->willReturnCallback(static function (string $key, mixed $value) use ($qb, &$params): QueryBuilder {
                $params[$key] = $value;

                return $qb;
            });

        $qb->method('andWhere')
            ->willReturnCallback(static function (string|\Stringable $expr) use ($qb, &$wheres): QueryBuilder {
                $wheres[] = (string) $expr;

                return $qb;
            });

        return $qb;
    }

    /**
     * @param list<array{path: string, dir: string}> $orderBys Populated on orderBy() calls
     */
    private function createSortQbMock(array &$orderBys): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('resetDQLPart')->willReturn($qb);

        $qb->method('orderBy')
            ->willReturnCallback(static function (string $path, string $dir) use ($qb, &$orderBys): QueryBuilder {
                $orderBys[] = ['path' => $path, 'dir' => $dir];

                return $qb;
            });

        return $qb;
    }

    // -------------------------------------------------------------------------
    // search — joined alias path
    // -------------------------------------------------------------------------

    public function testSearchOnJoinedAliasUsesGivenPathInsteadOfRootAlias(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createSearchQbMock($params, $wheres);

        $this->repo->applySearchPublic($qb, 'c', 'john', ['p.email']);

        self::assertCount(1, $wheres);
        self::assertSame('ILIKE(p.email, :q) = TRUE', $wheres[0]);
    }

    // -------------------------------------------------------------------------
    // search — backward compatibility (no dot)
    // -------------------------------------------------------------------------

    public function testSearchWithoutDotStillResolvesAgainstRootAlias(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createSearchQbMock($params, $wheres);

        $this->repo->applySearchPublic($qb, 'c', 'john', ['firstName']);

        self::assertCount(1, $wheres);
        self::assertSame('ILIKE(c.firstName, :q) = TRUE', $wheres[0]);
    }

    public function testSearchMixesDottedAndPlainFieldsInSameCall(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createSearchQbMock($params, $wheres);

        $this->repo->applySearchPublic($qb, 'c', 'john', ['firstName', 'p.email']);

        self::assertCount(1, $wheres);
        self::assertSame('ILIKE(c.firstName, :q) = TRUE OR ILIKE(p.email, :q) = TRUE', $wheres[0]);
    }

    // -------------------------------------------------------------------------
    // search — minSearchLength still enforced
    // -------------------------------------------------------------------------

    public function testSearchBelowMinLengthAddsNoCondition(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createSearchQbMock($params, $wheres);

        $this->repo->applySearchPublic($qb, 'c', 'jo', ['p.email'], 3);

        self::assertSame([], $wheres);
        self::assertSame([], $params);
    }

    // -------------------------------------------------------------------------
    // sort — joined alias via SortInput
    // -------------------------------------------------------------------------

    public function testSortOnJoinedAliasUsesTheDeclaredAliasInsteadOfRootAlias(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->repo->applySortPublic($qb, 'c', 'lastName', 'asc', ['lastName' => SortInput::field(alias: 'p')]);

        self::assertCount(1, $orderBys);
        self::assertSame('p.lastName', $orderBys[0]['path']);
        self::assertSame('ASC', $orderBys[0]['dir']);
    }

    public function testSortNotInAllowlistIsIgnored(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->repo->applySortPublic($qb, 'c', 'p.lastName', 'asc', ['createdAt' => SortInput::field()]);

        self::assertSame([], $orderBys);
    }

    public function testPlainStringSortableEntryThrows(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name');

        $this->repo->applySortPublic($qb, 'c', 'name', 'asc', ['name']);
    }

    // -------------------------------------------------------------------------
    // sort — SortInput (public name as key, alias kept internal)
    // -------------------------------------------------------------------------

    public function testSortInputResolvesTheDeclaredAliasAndKeepsTheClientNameOutOfIt(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->repo->applySortPublic($qb, 'cr', 'legalName', 'asc', ['legalName' => SortInput::field(alias: 'c')]);

        self::assertCount(1, $orderBys);
        self::assertSame('c.legalName', $orderBys[0]['path']);
        self::assertSame('ASC', $orderBys[0]['dir']);
    }

    public function testSortInputWithoutAliasUsesTheRootAlias(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->repo->applySortPublic($qb, 'cr', 'createdAt', 'desc', ['createdAt' => SortInput::field()]);

        self::assertSame('cr.createdAt', $orderBys[0]['path']);
        self::assertSame('DESC', $orderBys[0]['dir']);
    }

    public function testSortInputExplicitPropertyDiffersFromThePublicName(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $sortable = [
            'company_name' => SortInput::field(alias: 'c', property: 'name'),
            'contact_name' => SortInput::field(alias: 'ct', property: 'name'),
        ];
        $this->repo->applySortPublic($qb, 'cr', 'contact_name', 'asc', $sortable);

        self::assertSame('ct.name', $orderBys[0]['path']);
    }

    public function testSortInputConvertsTheKeyWithThePropertyCase(): void
    {
        PropertyCaseRegistry::set(PropertyCase::Camel);

        try {
            $orderBys = [];
            $qb = $this->createSortQbMock($orderBys);

            $this->repo->applySortPublic($qb, 'cr', 'legal_name', 'asc', ['legal_name' => SortInput::field(alias: 'c')]);

            self::assertSame('c.legalName', $orderBys[0]['path']);
        } finally {
            PropertyCaseRegistry::set(PropertyCase::None);
        }
    }

    public function testSortInputExpressionIsOrderedByAsIs(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);
        $expression = "CONCAT(u.firstName, ' ', u.lastName)";

        $this->repo->applySortPublic($qb, 'cr', 'full_name', 'desc', ['full_name' => SortInput::field(expression: $expression)]);

        self::assertSame($expression, $orderBys[0]['path']);
        self::assertSame('DESC', $orderBys[0]['dir']);
    }

    public function testSortInputIsNotMatchedByItsQualifiedPath(): void
    {
        $orderBys = [];
        $qb = $this->createSortQbMock($orderBys);

        $this->repo->applySortPublic($qb, 'cr', 'c.legalName', 'asc', ['legalName' => SortInput::field(alias: 'c')]);

        self::assertSame([], $orderBys);
    }
}
