<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Trait\Repository;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Letkode\OrmToolkitBundle\Trait\Repository\BaseRepositoryTrait;
use Letkode\QueryFilterBundle\Filter\FilterCriteria;
use Letkode\QueryFilterBundle\Filter\FilterInput;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the private applyFilters() method for testing without touching visibility in the trait.
 */
final class FilterTestRepository
{
    use BaseRepositoryTrait;

    public function applyFiltersPublic(QueryBuilder $qb, string $alias, array $filters, array $filterable): void
    {
        $this->applyFilters($qb, $alias, $filters, $filterable);
    }
}

final class BaseRepositoryTraitFilterTest extends TestCase
{
    private FilterTestRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new FilterTestRepository();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a QB mock that captures setParameter() and andWhere() calls.
     *
     * @param array<string, mixed> $params  Populated on setParameter() calls
     * @param list<string>         $wheres  Populated on andWhere() calls (cast to string)
     */
    private function createQbMock(array &$params, array &$wheres): QueryBuilder
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->method('setParameter')
            ->willReturnCallback(static function (string $key, mixed $value) use ($qb, &$params): QueryBuilder {
                $params[$key] = $value;

                return $qb;
            });

        $qb->method('andWhere')
            ->willReturnCallback(static function (mixed $expr) use ($qb, &$wheres): QueryBuilder {
                $wheres[] = (string) $expr;

                return $qb;
            });

        return $qb;
    }

    // -------------------------------------------------------------------------
    // number — between
    // -------------------------------------------------------------------------

    public function testBetweenOperatorOnNumberType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('price', 'between', ['10', '50'])],
            ['price' => FilterInput::number()],
        );

        self::assertSame(10.0, $params['filter_price_0_from']);
        self::assertSame(50.0, $params['filter_price_0_to']);
        self::assertCount(1, $wheres);
        self::assertSame('u.price BETWEEN :filter_price_0_from AND :filter_price_0_to', $wheres[0]);
    }

    public function testNotBetweenOperatorOnNumberType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('price', 'not_between', ['10', '50'])],
            ['price' => FilterInput::number()],
        );

        self::assertSame(10.0, $params['filter_price_0_from']);
        self::assertSame(50.0, $params['filter_price_0_to']);
        self::assertCount(1, $wheres);
        self::assertSame('u.price NOT BETWEEN :filter_price_0_from AND :filter_price_0_to', $wheres[0]);
    }

    // -------------------------------------------------------------------------
    // date — between, not_between, before, after
    // -------------------------------------------------------------------------

    public function testBetweenOperatorOnDateType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('createdAt', 'between', ['2024-01-01', '2024-12-31'])],
            ['createdAt' => FilterInput::date()],
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0_from']);
        self::assertSame('2024-01-01', $params['filter_createdAt_0_from']->format('Y-m-d'));
        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0_to']);
        self::assertSame('2024-12-31', $params['filter_createdAt_0_to']->format('Y-m-d'));
        self::assertCount(1, $wheres);
        self::assertSame('u.createdAt BETWEEN :filter_createdAt_0_from AND :filter_createdAt_0_to', $wheres[0]);
    }

    public function testNotBetweenOperatorOnDateType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('createdAt', 'not_between', ['2024-01-01', '2024-12-31'])],
            ['createdAt' => FilterInput::date()],
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0_from']);
        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0_to']);
        self::assertCount(1, $wheres);
        self::assertSame('u.createdAt NOT BETWEEN :filter_createdAt_0_from AND :filter_createdAt_0_to', $wheres[0]);
    }

    public function testBeforeOperatorOnDateType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('createdAt', 'before', ['2024-12-31'])],
            ['createdAt' => FilterInput::date()],
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0']);
        self::assertSame('2024-12-31', $params['filter_createdAt_0']->format('Y-m-d'));
        self::assertCount(1, $wheres);
        self::assertSame('u.createdAt < :filter_createdAt_0', $wheres[0]);
    }

    public function testAfterOperatorOnDateType(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('createdAt', 'after', ['2024-01-01'])],
            ['createdAt' => FilterInput::date()],
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $params['filter_createdAt_0']);
        self::assertSame('2024-01-01', $params['filter_createdAt_0']->format('Y-m-d'));
        self::assertCount(1, $wheres);
        self::assertSame('u.createdAt > :filter_createdAt_0', $wheres[0]);
    }

    // -------------------------------------------------------------------------
    // OR/AND composition logic
    // -------------------------------------------------------------------------

    public function testSameOperatorTwoConditionsUsesOrLogic(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        // Two between on the same field with same operator → OR
        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [
                new FilterCriteria('price', 'between', ['10', '50']),
                new FilterCriteria('price', 'between', ['100', '200']),
            ],
            ['price' => FilterInput::number()],
        );

        self::assertSame(10.0, $params['filter_price_0_from']);
        self::assertSame(50.0, $params['filter_price_0_to']);
        self::assertSame(100.0, $params['filter_price_1_from']);
        self::assertSame(200.0, $params['filter_price_1_to']);
        self::assertCount(1, $wheres);
        self::assertSame(
            '(u.price BETWEEN :filter_price_0_from AND :filter_price_0_to) OR (u.price BETWEEN :filter_price_1_from AND :filter_price_1_to)',
            $wheres[0],
        );
    }

    public function testDifferentOperatorsTwoConditionsUsesAndLogic(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        // before + after on the same date field → AND
        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [
                new FilterCriteria('createdAt', 'after', ['2024-01-01']),
                new FilterCriteria('createdAt', 'before', ['2024-12-31']),
            ],
            ['createdAt' => FilterInput::date()],
        );

        self::assertCount(1, $wheres);
        self::assertSame(
            'u.createdAt > :filter_createdAt_0 AND u.createdAt < :filter_createdAt_1',
            $wheres[0],
        );
    }

    public function testMixedOperatorsGroupsSameWithOrThenAnd(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        // two between (→ OR) + one before (→ AND with the OR group)
        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [
                new FilterCriteria('createdAt', 'between', ['2024-01-01', '2024-03-31']),
                new FilterCriteria('createdAt', 'between', ['2024-07-01', '2024-09-30']),
                new FilterCriteria('createdAt', 'before', ['2025-01-01']),
            ],
            ['createdAt' => FilterInput::date()],
        );

        self::assertCount(1, $wheres);
        self::assertSame(
            '((u.createdAt BETWEEN :filter_createdAt_0_from AND :filter_createdAt_0_to) OR (u.createdAt BETWEEN :filter_createdAt_1_from AND :filter_createdAt_1_to)) AND u.createdAt < :filter_createdAt_2',
            $wheres[0],
        );
    }

    // -------------------------------------------------------------------------
    // field not in filterable is silently ignored
    // -------------------------------------------------------------------------

    public function testUnknownFilterFieldIsIgnored(): void
    {
        $params = [];
        $wheres = [];
        $qb = $this->createQbMock($params, $wheres);

        $this->repo->applyFiltersPublic(
            $qb,
            'u',
            [new FilterCriteria('unknown', 'between', ['1', '9'])],
            ['price' => FilterInput::number()],
        );

        self::assertSame([], $params);
        self::assertSame([], $wheres);
    }
}
