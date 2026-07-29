<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Trait\Repository;

use Doctrine\ORM\QueryBuilder;

trait TranslatableRepositoryTrait
{
    /**
     * Adds ORDER BY COALESCE(TRANSLATE_FIELD_VALUE(translations, field, locale), field_column).
     * Falls back to the original column when the translation is missing for the given locale.
     *
     * Uses a HIDDEN SELECT alias because Doctrine DQL does not allow COALESCE()
     * with custom functions directly in ORDER BY.
     */
    protected function addTranslatedOrderBy(
        QueryBuilder $qb,
        string $alias,
        string $field,
        string $locale,
        string $direction = 'ASC',
    ): void {
        $param = 'tr_locale_' . $field;
        $hidden = 'tr_order_' . $field;

        $qb
            ->addSelect(\sprintf(
                "COALESCE(TRANSLATE_FIELD_VALUE(%s.translations, '%s', :%s), %s.%s) AS HIDDEN %s",
                $alias,
                $field,
                $param,
                $alias,
                $field,
                $hidden,
            ))
            ->addOrderBy($hidden, $direction)
            ->setParameter($param, $locale);
    }

    /**
     * Adds WHERE LOWER(COALESCE(TRANSLATE_FIELD_VALUE(translations, field, locale), field_column)) LIKE :term.
     * Searches in the translated value and falls back to the original column.
     */
    protected function addTranslatedSearch(
        QueryBuilder $qb,
        string $alias,
        string $field,
        string $term,
        string $locale,
    ): void {
        $localeParam = 'tr_locale_' . $field;
        $termParam = 'tr_term_' . $field;
        $qb
            ->andWhere(\sprintf(
                "LOWER(COALESCE(TRANSLATE_FIELD_VALUE(%s.translations, '%s', :%s), %s.%s)) LIKE :%s",
                $alias,
                $field,
                $localeParam,
                $alias,
                $field,
                $termParam,
            ))
            ->setParameter($localeParam, $locale)
            ->setParameter($termParam, '%' . strtolower($term) . '%');
    }
}
