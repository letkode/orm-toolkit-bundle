# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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
