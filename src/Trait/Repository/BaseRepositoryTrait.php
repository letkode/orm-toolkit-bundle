<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Letkode\HttpExceptionBundle\Exception\EntityNotFoundException;
use Letkode\ListQueryBundle\Exception\QueryParameterRejection;
use Letkode\ListQueryBundle\Exception\RejectionReason;
use Letkode\ListQueryBundle\Exception\UndeclaredQueryParameterException;
use Letkode\ListQueryBundle\Filter\FilterCastType;
use Letkode\ListQueryBundle\Filter\FilterCriteria;
use Letkode\ListQueryBundle\Filter\FilterInput;
use Letkode\ListQueryBundle\Naming\PropertyCaseRegistry;
use Letkode\ListQueryBundle\Request\ListQueryRequest;
use Letkode\ListQueryBundle\Result\PaginatedResult;
use Letkode\ListQueryBundle\Sort\SortInput;
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
     * @param array<string, SortInput>   $sortable   Allowed sort fields: `'publicName' => SortInput::field(...)` (the key is the
     *                                               name the client sends; its alias never reaches the API)
     * @param string[]                   $searchable Fields to apply ILIKE search on
     * @param array<string, FilterInput> $filterable Allowed filter fields and their definitions
     * @param bool                       $strict     When true (default), an undeclared sort/filter field, an
     *                                               unknown operator or a malformed filter is reported instead
     *                                               of silently ignored
     *
     * @return PaginatedResult<T>
     *
     * @throws UndeclaredQueryParameterException when $strict and the query carries rejected parameters
     */
    public function paginate(
        QueryBuilder $qb,
        ListQueryRequest $query,
        array $sortable = [],
        array $searchable = [],
        int $minSearchLength = 3,
        array $filterable = [],
        bool $strict = true,
    ): PaginatedResult {
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

        return new PaginatedResult($data, $total, $query->page, $query->perPage);
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
     * @param array<string, SortInput> $sortable
     *
     * @return list<QueryParameterRejection>
     */
    private function applySort(QueryBuilder $qb, string $alias, string|null $sort, string $dir, array $sortable): array
    {
        foreach ($sortable as $key => $entry) {
            // @phpstan-ignore instanceof.alwaysTrue (the declared type is not enforced at runtime)
            if (!\is_string($key) || !$entry instanceof SortInput) {
                throw new \InvalidArgumentException(\sprintf('sortable entries must be \'publicName\' => SortInput::field(...), got "%s".', \is_string($entry) ? $entry : (string) $key));
            }
        }

        if (null === $sort) {
            return [];
        }

        $path = $this->resolveSortPath($alias, $sort, $sortable);
        if (null === $path) {
            return [new QueryParameterRejection('sort', RejectionReason::NotSortable, $sort)];
        }

        $qb->resetDQLPart('orderBy')
            ->orderBy($path, strtoupper($dir));

        return [];
    }

    /**
     * @param array<string, SortInput> $sortable
     */
    private function resolveSortPath(string $alias, string $sort, array $sortable): string|null
    {
        $input = $sortable[$sort] ?? null;

        return null === $input ? null : $input->expression ?? ($input->alias ?? $alias) . '.' . $input->resolveProperty($sort);
    }

    /**
     * Resolves a sortable/searchable allowlist entry to a Doctrine property path.
     *
     * A field containing a dot is already a qualified path (e.g. `p.lastName`, mirroring
     * `FilterInput::alias`) and is used as-is; the caller is responsible for the referenced
     * alias being joined on the given QueryBuilder. A bare field name resolves against the
     * root alias, converted to the configured `property_case`.
     */
    private function resolvePath(string $alias, string $field): string
    {
        return str_contains($field, '.') ? $field : $alias . '.' . PropertyCaseRegistry::get()->convert($field);
    }

    /**
     * @param list<FilterCriteria>       $filters
     * @param array<string, FilterInput> $filterable
     *
     * @return list<QueryParameterRejection>
     */
    private function applyFilters(QueryBuilder $qb, string $alias, array $filters, array $filterable): array
    {
        foreach ($filterable as $key => $entry) {
            // @phpstan-ignore instanceof.alwaysTrue (the declared type is not enforced at runtime)
            if (!$entry instanceof FilterInput) {
                throw new \InvalidArgumentException(\sprintf('filterable entries must be \'key\' => FilterInput::*(...), got "%s".', $key));
            }
        }

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
            $path = $field->expression ?? ($field->alias ?? $alias) . '.' . $field->resolveProperty($fieldName);
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
