<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Repository;

use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\QueryBuilder;
use Letkode\CommonBundle\Exception\EntityNotFoundException;
use Letkode\QueryFilterBundle\Filter\FilterCriteria;
use Letkode\QueryFilterBundle\Filter\FilterInput;
use Letkode\QueryFilterBundle\Request\QueryRequest;
use Letkode\QueryFilterBundle\Result\PaginatedResult;
use Symfony\Component\Uid\Uuid;

/** @template T of object */
trait BaseRepositoryTrait
{
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
     * @param string[]                    $sortable   Allowed field names for sorting
     * @param string[]                    $searchable Fields to apply ILIKE search on
     * @param array<string, FilterInput>  $filterable Allowed filter fields and their definitions
     */
    public function paginate(
        QueryBuilder $qb,
        QueryRequest $query,
        array $sortable = [],
        array $searchable = [],
        int $minSearchLength = 3,
        array $filterable = [],
    ): PaginatedResult {
        $alias = $qb->getRootAliases()[0];

        $this->applySearch($qb, $alias, $query->q, $searchable, $minSearchLength);
        $this->applyFilters($qb, $alias, $query->filters, $filterable);
        $this->applySort($qb, $alias, $query->sort, $query->dir, $sortable);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT ' . $alias . '.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $data = $qb
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getResult();

        return new PaginatedResult($data, $total, $query->page, $query->perPage);
    }

    private function applySearch(QueryBuilder $qb, string $alias, string|null $q, array $searchable, int $minSearchLength): void
    {
        if (null === $q || mb_strlen($q) < $minSearchLength || [] === $searchable) {
            return;
        }

        $conditions = array_map(
            static fn (string $field) => 'ILIKE(' . $alias . '.' . $field . ', :q) = TRUE',
            $searchable,
        );

        $qb->andWhere(implode(' OR ', $conditions))
            ->setParameter('q', '%' . $q . '%');
    }

    private function applySort(QueryBuilder $qb, string $alias, string|null $sort, string $dir, array $sortable): void
    {
        if (null === $sort || !\in_array($sort, $sortable, true)) {
            return;
        }

        $qb->resetDQLPart('orderBy')
            ->orderBy($alias . '.' . $sort, strtoupper($dir));
    }

    /**
     * @param list<FilterCriteria>       $filters
     * @param array<string, FilterInput> $filterable
     */
    private function applyFilters(QueryBuilder $qb, string $alias, array $filters, array $filterable): void
    {
        $grouped = [];
        foreach ($filters as $criteria) {
            if (!isset($filterable[$criteria->field])) {
                continue;
            }
            $grouped[$criteria->field][] = $criteria;
        }

        foreach ($grouped as $fieldName => $criteriaList) {
            $field = $filterable[$fieldName];
            $path = $field->path ?? $alias . '.' . $fieldName;
            $this->applyFieldFilters($qb, $criteriaList, $field, $path);
        }
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

    private function buildFilterExpression(QueryBuilder $qb, FilterCriteria $criteria, FilterInput $field, string $path, int $idx): string|Composite|null
    {
        $op = $criteria->operator;
        $values = $criteria->values;
        $param = 'filter_' . preg_replace('/[^a-zA-Z0-9]/', '_', $criteria->field) . '_' . $idx;

        switch ($op) {
            case 'contains':
                $qb->setParameter($param, '%' . $values[0] . '%');

                return 'ILIKE(' . $path . ', :' . $param . ') = TRUE';

            case 'not_contains':
                $qb->setParameter($param, '%' . $values[0] . '%');

                return 'ILIKE(' . $path . ', :' . $param . ') = FALSE';

            case 'starts_with':
                $qb->setParameter($param, $values[0] . '%');

                return 'ILIKE(' . $path . ', :' . $param . ') = TRUE';

            case 'ends_with':
                $qb->setParameter($param, '%' . $values[0]);

                return 'ILIKE(' . $path . ', :' . $param . ') = TRUE';

            case 'is':
                $qb->setParameter($param, $field->castValue($values[0]));

                return $path . ' = :' . $param;

            case 'is_not':
                $qb->setParameter($param, $field->castValue($values[0]));

                return $path . ' != :' . $param;

            case 'empty':
                return 'text' === $field->type
                    ? $qb->expr()->orX($path . ' IS NULL', $path . " = ''")
                    : $path . ' IS NULL';

            case 'not_empty':
                return 'text' === $field->type
                    ? $qb->expr()->andX($path . ' IS NOT NULL', $path . " != ''")
                    : $path . ' IS NOT NULL';

            case 'between':
                $qb->setParameter($param . '_from', $field->castValue($values[0]));
                $qb->setParameter($param . '_to', $field->castValue($values[1]));

                return $path . ' BETWEEN :' . $param . '_from AND :' . $param . '_to';

            case 'not_between':
                $qb->setParameter($param . '_from', $field->castValue($values[0]));
                $qb->setParameter($param . '_to', $field->castValue($values[1]));

                return $path . ' NOT BETWEEN :' . $param . '_from AND :' . $param . '_to';

            case 'before':
                $qb->setParameter($param, $field->castValue($values[0]));

                return $path . ' < :' . $param;

            case 'after':
                $qb->setParameter($param, $field->castValue($values[0]));

                return $path . ' > :' . $param;

            case 'is_any_of':
                $qb->setParameter($param, $field->castValues($values));

                return $path . ' IN (:' . $param . ')';

            case 'is_not_any_of':
                $qb->setParameter($param, $field->castValues($values));

                return $path . ' NOT IN (:' . $param . ')';

            case 'includes_all':
                $qb->setParameter($param, $field->castValues($values));

                return 'CONTAINS(' . $path . ', :' . $param . ') = TRUE';

            case 'excludes_all':
                $qb->setParameter($param, $field->castValues($values));

                return 'CONTAINS(' . $path . ', :' . $param . ') = FALSE';

            default:
                return null;
        }
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
