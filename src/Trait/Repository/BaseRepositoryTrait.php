<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Letkode\CommonBundle\Exception\EntityNotFoundException;
use Letkode\QueryFilterBundle\Exception\QueryParameterRejection;
use Letkode\QueryFilterBundle\Exception\RejectionReason;
use Letkode\QueryFilterBundle\Exception\UndeclaredQueryParameterException;
use Letkode\QueryFilterBundle\Filter\FilterCastType;
use Letkode\QueryFilterBundle\Filter\FilterCriteria;
use Letkode\QueryFilterBundle\Filter\FilterInput;
use Letkode\QueryFilterBundle\Request\FilterQueryRequest;
use Letkode\QueryFilterBundle\Result\PaginatedResultRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @template T of object
 *
 * @method EntityManagerInterface getEntityManager()
 * @method T|null                 findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 */
trait BaseRepositoryTrait
{
    /**
     * Filter operators understood by buildFilterExpression(). Kept in sync with
     * its match() arms; the FilterOperatorCoverageTest guards against drift.
     */
    private const array FILTER_OPERATORS = [
        'contains', 'not_contains', 'starts_with', 'ends_with',
        'is', 'is_not', 'empty', 'not_empty',
        'between', 'not_between',
        'less_than', 'lt', 'before',
        'less_than_equal', 'lte',
        'greater_than', 'gt', 'after',
        'greater_than_equal', 'gte',
        'is_any_of', 'is_not_any_of', 'includes_all', 'excludes_all',
    ];

    /** @param T $entity */
    public function save(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @param T $entity */
    public function remove(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param string[]                   $sortable   Allowed field names for sorting
     * @param string[]                   $searchable Fields to apply ILIKE search on
     * @param array<string, FilterInput> $filterable Allowed filter fields and their definitions
     * @param bool                       $strict     When true (default), an undeclared sort/filter field, an
     *                                               unknown operator or a malformed filter is reported instead
     *                                               of silently ignored
     *
     * @return PaginatedResultRepository<T>
     *
     * @throws UndeclaredQueryParameterException when $strict and the query carries rejected parameters
     */
    public function paginate(
        QueryBuilder $qb,
        FilterQueryRequest $query,
        array $sortable = [],
        array $searchable = [],
        int $minSearchLength = 3,
        array $filterable = [],
        bool $strict = true,
    ): PaginatedResultRepository {
        $alias = $qb->getRootAliases()[0];

        $this->applySearch($qb, $alias, $query->q, $searchable, $minSearchLength);
        $rejections = [
            ...$this->applySort($qb, $alias, $query->sort, $query->dir, $sortable),
            ...$this->applyFilters($qb, $alias, $query->filters, $filterable),
            ...$query->rejected,
        ];

        if ($strict && [] !== $rejections) {
            throw new UndeclaredQueryParameterException(array_values($rejections));
        }

        $this->applyDeterministicOrder($qb, $alias);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT ' . $alias . '.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<T> $data */
        $data = $qb
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getResult();

        return new PaginatedResultRepository($data, $total, $query->page, $query->perPage);
    }

    /**
     * Appends the root id as the final ORDER BY tiebreaker so offset pagination
     * has a total ordering. Rows sharing the primary sort value — or a query with
     * no sort at all — otherwise come back in the database's physical row order,
     * which on PostgreSQL shifts after an UPDATE, letting rows repeat across pages
     * or be skipped. Skipped when the query already orders by the root id.
     */
    private function applyDeterministicOrder(QueryBuilder $qb, string $alias): void
    {
        $idPath = $alias . '.id';

        /** @var list<OrderBy> $orderBy */
        $orderBy = $qb->getDQLPart('orderBy');

        foreach ($orderBy as $part) {
            foreach ($part->getParts() as $clause) {
                if ($clause === $idPath || str_starts_with($clause, $idPath . ' ')) {
                    return;
                }
            }
        }

        $qb->addOrderBy($idPath, 'ASC');
    }

    /**
     * @param string[] $searchable
     */
    private function applySearch(QueryBuilder $qb, string $alias, string|null $q, array $searchable, int $minSearchLength): void
    {
        if (null === $q || mb_strlen($q) < $minSearchLength || [] === $searchable) {
            return;
        }

        $conditions = array_map(
            fn (string $field) => 'ILIKE(' . $this->resolvePath($alias, $field) . ', :q) = TRUE',
            $searchable,
        );

        $qb->andWhere(implode(' OR ', $conditions))
            ->setParameter('q', '%' . $q . '%');
    }

    /**
     * @param string[] $sortable
     *
     * @return list<QueryParameterRejection>
     */
    private function applySort(QueryBuilder $qb, string $alias, string|null $sort, string $dir, array $sortable): array
    {
        if (null === $sort) {
            return [];
        }

        if (!\in_array($sort, $sortable, true)) {
            return [new QueryParameterRejection('sort', RejectionReason::NotSortable, $sort)];
        }

        $qb->resetDQLPart('orderBy')
            ->orderBy($this->resolvePath($alias, $sort), strtoupper($dir));

        return [];
    }

    /**
     * Resolves a sortable/searchable allowlist entry to a Doctrine property path.
     *
     * A field containing a dot is already a qualified path (e.g. `p.lastName`, mirroring
     * `FilterInput::path`) and is used as-is; the caller is responsible for the referenced
     * alias being joined on the given QueryBuilder. A bare field name resolves against the
     * root alias, same as before.
     */
    private function resolvePath(string $alias, string $field): string
    {
        return str_contains($field, '.') ? $field : $alias . '.' . $field;
    }

    /**
     * @param list<FilterCriteria>       $filters
     * @param array<string, FilterInput> $filterable
     *
     * @return list<QueryParameterRejection>
     */
    private function applyFilters(QueryBuilder $qb, string $alias, array $filters, array $filterable): array
    {
        $grouped = [];
        $rejections = [];
        foreach ($filters as $criteria) {
            if (!isset($filterable[$criteria->field])) {
                $rejection = new QueryParameterRejection(
                    'filters.' . $criteria->field,
                    RejectionReason::NotFilterable,
                    $criteria->field,
                );
                $rejections[$this->rejectionKey($rejection)] = $rejection;

                continue;
            }

            if (!\in_array($criteria->operator, self::FILTER_OPERATORS, true)) {
                $rejection = new QueryParameterRejection(
                    'filters.' . $criteria->field,
                    RejectionReason::UnknownOperator,
                    $criteria->operator,
                );
                $rejections[$this->rejectionKey($rejection)] = $rejection;

                continue;
            }

            $grouped[$criteria->field][] = $criteria;
        }

        foreach ($grouped as $fieldName => $criteriaList) {
            $field = $filterable[$fieldName];
            $path = $field->path ?? $alias . '.' . $fieldName;
            $this->applyFieldFilters($qb, $criteriaList, $field, $path);
        }

        return array_values($rejections);
    }

    private function rejectionKey(QueryParameterRejection $rejection): string
    {
        return $rejection->parameter . '|' . $rejection->reason->value . '|' . ($rejection->value ?? '');
    }

    /**
     * @param list<FilterCriteria> $criteriaList
     */
    private function applyFieldFilters(QueryBuilder $qb, array $criteriaList, FilterInput $field, string $path): void
    {
        if (1 === \count($criteriaList)) {
            $expr = $this->buildFilterExpression($qb, $criteriaList[0], $field, $path, 0);
            if (null !== $expr) {
                $qb->andWhere($expr);
            }

            return;
        }

        $byOperator = [];
        foreach ($criteriaList as $idx => $criteria) {
            $byOperator[$criteria->operator][] = [$idx, $criteria];
        }

        $andParts = [];
        foreach ($byOperator as $items) {
            $orParts = [];
            foreach ($items as [$idx, $criteria]) {
                $expr = $this->buildFilterExpression($qb, $criteria, $field, $path, $idx);
                if (null !== $expr) {
                    $orParts[] = $expr;
                }
            }

            if ([] === $orParts) {
                continue;
            }

            $andParts[] = 1 === \count($orParts)
                ? $orParts[0]
                : $qb->expr()->orX(...$orParts);
        }

        if ([] !== $andParts) {
            $qb->andWhere(1 === \count($andParts)
                ? $andParts[0]
                : $qb->expr()->andX(...$andParts));
        }
    }

    private function buildFilterExpression(QueryBuilder $qb, FilterCriteria $criteria, FilterInput $field, string $path, int $idx): string|Andx|Orx|null
    {
        $op = $criteria->operator;
        $values = $criteria->values;
        $param = 'filter_' . preg_replace('/[^a-zA-Z0-9]/', '_', $criteria->field) . '_' . $idx;

        match ($op) {
            'contains', 'not_contains' => $qb->setParameter($param, '%' . $values[0] . '%'),
            'starts_with' => $qb->setParameter($param, $values[0] . '%'),
            'ends_with' => $qb->setParameter($param, '%' . $values[0]),
            'is', 'is_not',
            'less_than', 'lt', 'before',
            'less_than_equal', 'lte',
            'greater_than', 'gt', 'after',
            'greater_than_equal', 'gte' => $qb->setParameter($param, $field->castValue($values[0])),
            'is_any_of', 'is_not_any_of', 'includes_all', 'excludes_all' => $qb->setParameter($param, $field->castValues($values)),
            'between', 'not_between' => $qb
                ->setParameter($param . '_from', $field->castValue($values[0]))
                ->setParameter($param . '_to', $field->castValue($values[1])),
            default => null,
        };

        return match ($op) {
            'contains', 'starts_with', 'ends_with' => 'ILIKE(' . $path . ', :' . $param . ') = TRUE',
            'not_contains' => 'ILIKE(' . $path . ', :' . $param . ') = FALSE',
            'is' => $path . ' = :' . $param,
            'is_not' => $path . ' != :' . $param,
            'empty' => FilterCastType::Text === $field->type
                ? $qb->expr()->orX($path . ' IS NULL', $path . " = ''")
                : $path . ' IS NULL',
            'not_empty' => FilterCastType::Text === $field->type
                ? $qb->expr()->andX($path . ' IS NOT NULL', $path . " != ''")
                : $path . ' IS NOT NULL',
            'between' => $path . ' BETWEEN :' . $param . '_from AND :' . $param . '_to',
            'not_between' => $path . ' NOT BETWEEN :' . $param . '_from AND :' . $param . '_to',
            'less_than', 'lt', 'before' => $path . ' < :' . $param,
            'less_than_equal', 'lte' => $path . ' <= :' . $param,
            'greater_than', 'gt', 'after' => $path . ' > :' . $param,
            'greater_than_equal', 'gte' => $path . ' >= :' . $param,
            'is_any_of' => $path . ' IN (:' . $param . ')',
            'is_not_any_of' => $path . ' NOT IN (:' . $param . ')',
            'includes_all' => 'CONTAINS(' . $path . ', :' . $param . ') = TRUE',
            'excludes_all' => 'CONTAINS(' . $path . ', :' . $param . ') = FALSE',
            default => null,
        };
    }

    /** @return T|null */
    public function findByUuid(Uuid $uuid): object|null
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /** @return T */
    public function findOrFailByUuid(Uuid $uuid, string $message = 'Not found.'): object
    {
        $entity = $this->findByUuid($uuid);

        if (null === $entity) {
            throw new EntityNotFoundException($message);
        }

        return $entity;
    }
}
