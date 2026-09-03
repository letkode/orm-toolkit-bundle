<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Tests\Trait\Repository;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Letkode\OrmToolkitBundle\Trait\Repository\BaseRepositoryTrait;
use Letkode\QueryFilterBundle\Exception\QueryParameterRejection;
use Letkode\QueryFilterBundle\Exception\RejectionReason;
use Letkode\QueryFilterBundle\Exception\UndeclaredQueryParameterException;
use Letkode\QueryFilterBundle\Filter\FilterCriteria;
use Letkode\QueryFilterBundle\Filter\FilterInput;
use Letkode\QueryFilterBundle\Request\FilterQueryRequest;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the private apply* helpers and drives paginate() through a mock QB.
 */
final class StrictTestRepository
{
    /** @use BaseRepositoryTrait<object> */
    use BaseRepositoryTrait;

    /**
     * @param list<FilterCriteria>       $filters
     * @param array<string, FilterInput> $filterable
     *
     * @return list<QueryParameterRejection>
     */
    public function applyFiltersPublic(QueryBuilder $qb, string $alias, array $filters, array $filterable): array
    {
        return $this->applyFilters($qb, $alias, $filters, $filterable);
    }

    /**
     * @param string[] $sortable
     *
     * @return list<QueryParameterRejection>
     */
    public function applySortPublic(QueryBuilder $qb, string $alias, string|null $sort, string $dir, array $sortable): array
    {
        return $this->applySort($qb, $alias, $sort, $dir, $sortable);
    }

    public function buildFilterExpressionPublic(QueryBuilder $qb, FilterCriteria $criteria, FilterInput $field): string|object|null
    {
        return $this->buildFilterExpression($qb, $criteria, $field, 'u.field', 0);
    }

    public function applyDeterministicOrderPublic(QueryBuilder $qb, string $alias): void
    {
        $this->applyDeterministicOrder($qb, $alias);
    }

    /** @return list<string> */
    public function filterOperators(): array
    {
        return self::FILTER_OPERATORS;
    }
}

/** Sentinel thrown by the mock QB the moment a DQL query would be executed. */
final class QueryExecuted extends \RuntimeException
{
}

final class BaseRepositoryTraitStrictTest extends TestCase
{
    private StrictTestRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new StrictTestRepository();
    }

    // -------------------------------------------------------------------------
    // applySort()
    // -------------------------------------------------------------------------

    public function testApplySortRejectsUndeclaredField(): void
    {
        $wheres = [];
        $rejections = $this->repo->applySortPublic($this->qb($wheres), 'u', 'inventado', 'asc', ['name', 'createdAt']);

        self::assertCount(1, $rejections);
        self::assertSame('sort', $rejections[0]->parameter);
        self::assertSame(RejectionReason::NotSortable, $rejections[0]->reason);
        self::assertSame('inventado', $rejections[0]->value);
    }

    public function testApplySortWithNullSortReturnsNoRejections(): void
    {
        $wheres = [];
        self::assertSame([], $this->repo->applySortPublic($this->qb($wheres), 'u', null, 'asc', ['name']));
    }

    public function testApplySortWithDeclaredFieldReturnsNoRejections(): void
    {
        $wheres = [];
        self::assertSame([], $this->repo->applySortPublic($this->qb($wheres), 'u', 'name', 'asc', ['name']));
    }

    // -------------------------------------------------------------------------
    // applyFilters()
    // -------------------------------------------------------------------------

    public function testApplyFiltersRejectsUndeclaredFieldAndSkipsIt(): void
    {
        $wheres = [];
        $rejections = $this->repo->applyFiltersPublic(
            $this->qb($wheres),
            'u',
            [new FilterCriteria('etapa', 'is', ['x'])],
            ['estado' => FilterInput::text()],
        );

        self::assertCount(1, $rejections);
        self::assertSame('filters.etapa', $rejections[0]->parameter);
        self::assertSame(RejectionReason::NotFilterable, $rejections[0]->reason);
        self::assertSame('etapa', $rejections[0]->value);
        self::assertSame([], $wheres, 'the undeclared filter must not reach the QueryBuilder');
    }

    public function testApplyFiltersRejectsUnknownOperator(): void
    {
        $wheres = [];
        $rejections = $this->repo->applyFiltersPublic(
            $this->qb($wheres),
            'u',
            [new FilterCriteria('nombre', 'wat', ['x'])],
            ['nombre' => FilterInput::text()],
        );

        self::assertCount(1, $rejections);
        self::assertSame('filters.nombre', $rejections[0]->parameter);
        self::assertSame(RejectionReason::UnknownOperator, $rejections[0]->reason);
        self::assertSame('wat', $rejections[0]->value);
        self::assertSame([], $wheres);
    }

    public function testApplyFiltersDeduplicatesIdenticalRejections(): void
    {
        $wheres = [];
        $rejections = $this->repo->applyFiltersPublic(
            $this->qb($wheres),
            'u',
            [
                new FilterCriteria('etapa', 'is', ['a']),
                new FilterCriteria('etapa', 'is_not', ['b']),
            ],
            ['estado' => FilterInput::text()],
        );

        self::assertCount(1, $rejections);
        self::assertSame('filters.etapa', $rejections[0]->parameter);
    }

    public function testApplyFiltersValidCriteriaProducesNoRejections(): void
    {
        $wheres = [];
        $rejections = $this->repo->applyFiltersPublic(
            $this->qb($wheres),
            'u',
            [new FilterCriteria('nombre', 'is', ['x'])],
            ['nombre' => FilterInput::text()],
        );

        self::assertSame([], $rejections);
        self::assertCount(1, $wheres);
    }

    // -------------------------------------------------------------------------
    // paginate() strict flag
    // -------------------------------------------------------------------------

    public function testPaginateStrictThrowsBeforeExecutingAnyQuery(): void
    {
        $query = new FilterQueryRequest(
            sort: 'inventado',
            filters: [new FilterCriteria('etapa', 'is', ['x'])],
        );

        try {
            $this->repo->paginate($this->paginateQb(), $query, sortable: ['name'], filterable: ['estado' => FilterInput::text()]);
            self::fail('expected UndeclaredQueryParameterException');
        } catch (UndeclaredQueryParameterException $e) {
            $params = array_map(static fn (QueryParameterRejection $r): string => $r->parameter, $e->rejections);
            self::assertContains('sort', $params);
            self::assertContains('filters.etapa', $params);
        }
    }

    public function testPaginateNonStrictSkipsRejectionsAndReachesQuery(): void
    {
        $query = new FilterQueryRequest(
            sort: 'inventado',
            filters: [new FilterCriteria('etapa', 'is', ['x'])],
        );

        // strict:false must NOT throw UndeclaredQueryParameterException; instead the
        // mock QB's sentinel proves execution proceeded past the rejection gate.
        $this->expectException(QueryExecuted::class);
        $this->repo->paginate(
            $this->paginateQb(),
            $query,
            sortable: ['name'],
            filterable: ['estado' => FilterInput::text()],
            strict: false,
        );
    }

    public function testPaginateStrictPassesWhenEverythingIsDeclared(): void
    {
        $query = new FilterQueryRequest(sort: 'name', filters: [new FilterCriteria('estado', 'is', ['x'])]);

        $this->expectException(QueryExecuted::class);
        $this->repo->paginate(
            $this->paginateQb(),
            $query,
            sortable: ['name'],
            filterable: ['estado' => FilterInput::text()],
        );
    }

    // -------------------------------------------------------------------------
    // FILTER_OPERATORS stays in sync with buildFilterExpression()
    // -------------------------------------------------------------------------

    public function testEveryDeclaredOperatorIsHandledByBuildFilterExpression(): void
    {
        $wheres = [];
        $qb = $this->qb($wheres);
        $field = FilterInput::text();

        foreach ($this->repo->filterOperators() as $operator) {
            $criteria = new FilterCriteria('field', $operator, ['1', '2']);
            self::assertNotNull(
                $this->repo->buildFilterExpressionPublic($qb, $criteria, $field),
                \sprintf('operator "%s" is in FILTER_OPERATORS but buildFilterExpression() returns null for it', $operator),
            );
        }
    }

    public function testUnknownOperatorIsNotInFilterOperators(): void
    {
        self::assertNotContains('wat', $this->repo->filterOperators());
    }

    // -------------------------------------------------------------------------
    // applyDeterministicOrder() — total ordering for offset pagination
    // -------------------------------------------------------------------------

    public function testDeterministicOrderAppendsRootIdWhenQueryHasNoOrderBy(): void
    {
        $added = [];
        $this->repo->applyDeterministicOrderPublic($this->orderByQb([], $added), 'u');

        self::assertSame(['u.id ASC'], $added);
    }

    public function testDeterministicOrderAppendsRootIdWhenSortingByAnotherColumn(): void
    {
        $added = [];
        $this->repo->applyDeterministicOrderPublic($this->orderByQb([new OrderBy('u.name', 'ASC')], $added), 'u');

        self::assertSame(['u.id ASC'], $added);
    }

    public function testDeterministicOrderSkippedWhenAlreadyOrderingByRootId(): void
    {
        $added = [];
        $this->repo->applyDeterministicOrderPublic($this->orderByQb([new OrderBy('u.id', 'DESC')], $added), 'u');

        self::assertSame([], $added);
    }

    public function testDeterministicOrderStillAppendsWhenOrderingByAJoinedId(): void
    {
        $added = [];
        // p.id is not unique per root row (many-to-one), so it is not a valid tiebreaker
        $this->repo->applyDeterministicOrderPublic($this->orderByQb([new OrderBy('p.id', 'ASC')], $added), 'u');

        self::assertSame(['u.id ASC'], $added);
    }

    /**
     * @param list<OrderBy> $orderBy
     * @param list<string>  $added   populated with "<sort> <order>" on addOrderBy()
     */
    private function orderByQb(array $orderBy, array &$added): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn($orderBy);
        $qb->method('addOrderBy')->willReturnCallback(
            static function (string|OrderBy $sort, string|null $order = null) use ($qb, &$added): QueryBuilder {
                $added[] = trim($sort . ' ' . ($order ?? ''));

                return $qb;
            },
        );

        return $qb;
    }

    /**
     * @param list<string> $wheres
     */
    private function qb(array &$wheres): QueryBuilder
    {
        $expr = new Expr();
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->method('setParameter')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('resetDQLPart')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('orderBy')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('andWhere')->willReturnCallback(static function (string|\Stringable $e) use ($qb, &$wheres): QueryBuilder {
            $wheres[] = (string) $e;

            return $qb;
        });

        return $qb;
    }

    private function paginateQb(): QueryBuilder
    {
        $expr = new Expr();
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn(['u']);
        $qb->method('expr')->willReturn($expr);
        $qb->method('setParameter')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('resetDQLPart')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('orderBy')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('addOrderBy')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('select')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('setFirstResult')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('setMaxResults')->willReturnCallback(static fn (): QueryBuilder => $qb);
        $qb->method('getQuery')->willReturnCallback(static function (): never {
            throw new QueryExecuted('a DQL query would have been executed');
        });

        return $qb;
    }
}
