# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.4.0] - 2026-08-18

### Added
- `BaseRepositoryTrait::paginate()` — `sortable` and `searchable` now accept a qualified alias path (e.g. `'p.lastName'`), matching the join-aware behavior `filterable` already had via `FilterInput::path`. A bare field name still resolves against the root alias; fully backward compatible.

---

## [1.3.0] - 2026-08-18

### Changed
- `BaseRepositoryTrait::paginate()` now returns `Letkode\QueryFilterBundle\Result\PaginatedResultRepository` — follows the rename in `letkode/query-filter-bundle` `1.3.0` (was `Result\PaginatedResult`)
- `letkode/query-filter-bundle` requirement bumped to `^1.3.0`

### BC breaks
- Code type-hinting against `Letkode\QueryFilterBundle\Result\PaginatedResult` (return of `paginate()`) must be updated to `PaginatedResultRepository`

---

## [1.2.2] - 2026-08-10

### Fixed
- `BaseRepositoryTrait::paginate()` return type documented as `PaginatedResult<T>` — `letkode/query-filter-bundle` `1.2.1` made `PaginatedResult` generic, which surfaced a new phpstan finding here. Backward compatible; no runtime change.
- Verified against `letkode/common-bundle` `1.4.2`, `letkode/helpers-bundle` `1.0.2` and `letkode/query-filter-bundle` `1.2.1` — 72 tests and phpstan level 9 pass unchanged.

---

## [1.2.1] - 2026-08-10

### Fixed
- `phpstan.neon` added (was required as a dev dependency but never configured); package is now phpstan level 9 clean
- `BaseRepositoryTrait::buildFilterExpression()` return type narrowed from `Doctrine\ORM\Query\Expr\Composite` to `Andx|Orx|string|null`, matching what it actually returns
- `BaseRepositoryTrait::paginate()` no longer accepts an untyped result from Doctrine's `getResult()` without narrowing
- `TranslateFieldValue` AST properties typed as `Doctrine\ORM\Query\AST\Node|string` instead of `mixed`
- `UuidGeneratorSubscriber::prePersist()` and `LetkodeOrmToolkitBundle::loadExtension()` missing parameter types documented
- `ValueObject\Phone` and `ValueObject\Slug` normalization no longer relies on `preg_replace()`'s nullable return going unchecked

No behavior changes; all fixes are type-safety only. 72 tests unchanged and passing.

---

## [1.2.0] - 2026-07-29

### Changed
- `BaseRepositoryTrait::paginate()` now type-hints `Letkode\QueryFilterBundle\Request\FilterQueryRequest` (renamed from `QueryFilterRequest` in `letkode/query-filter-bundle` `1.2.0`)
- `BaseRepositoryTrait::buildFilterExpression()` now compares `$field->type` against `Letkode\QueryFilterBundle\Filter\FilterCastType::Text` instead of the string `'text'` (`FilterInput::$type` is now a `FilterCastType` enum in `letkode/query-filter-bundle` `1.2.0`)

### Requires
- `letkode/query-filter-bundle` `^1.2`

---

## [1.1.0] - 2026-07-29

### Changed
- `BaseRepositoryTrait::paginate()` now type-hints `Letkode\QueryFilterBundle\Request\QueryFilterRequest` (renamed from `QueryRequest` in `letkode/query-filter-bundle` `1.1.0`)

### Requires
- `letkode/query-filter-bundle` `^1.1`

---

## [1.0.0] - 2026-07-28

### Added
- Initial release, successor to `letkode/entity-traits-bundle` (Doctrine traits, value objects and DQL utilities, without the query/filter DTOs, which moved to `letkode/query-filter-bundle`)
- Entity traits: `UuidTrait`, `HasTranslationsTrait`, `ParameterTrait`, `ObjectTrackNullableTrait`, `ObjectTrackRequiredTrait`
- Repository traits: `BaseRepositoryTrait`, `TranslatableRepositoryTrait`
- Value objects: `Email`, `Phone`, `Slug`, `Username`
- `TRANSLATE_FIELD_VALUE` Doctrine DQL function and `UuidGeneratorSubscriber`
- Symfony bundle integration via `LetkodeOrmToolkitBundle` extending `AbstractBundle`
- Auto-discovery support via `extra.symfony.bundles` in Composer
