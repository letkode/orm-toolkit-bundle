# letkode/orm-toolkit-bundle

Doctrine entity traits, repository traits, value objects and DQL utilities for Symfony applications.

---

## Installation

```bash
composer require letkode/orm-toolkit-bundle
```

Symfony Flex will register the bundle automatically. If not using Flex, add it manually:

```php
// config/bundles.php
return [
    Letkode\OrmToolkitBundle\LetkodeOrmToolkitBundle::class => ['all' => true],
];
```

The bundle automatically registers the `TRANSLATE_FIELD_VALUE` Doctrine DQL function via `PrependExtensionInterface`.

---

## Entity Traits

### `UuidTrait`

Adds a `uuid` column (UUIDv7) with a PostgreSQL `uuidv7()` default. The `UuidGeneratorSubscriber` ensures PHP-side generation before persist so Gedmo Loggable captures the value.

```php
use Letkode\OrmToolkitBundle\Trait\Entity\UuidTrait;

#[ORM\Entity]
class Product
{
    use UuidTrait;
}
```

> `HasTranslationsTrait` moved to `letkode/locale-bundle` (`Letkode\LocaleBundle\Trait\HasTranslationsTrait`) — see that package's README. `doctrine/orm` is `suggest`-only there, so it stays an opt-in dependency for apps that don't need entity-level translations.

### `ParameterTrait`

Adds a `parameters` jsonb column with recursive merge support.

```php
$entity->setParameter('color', 'red');
$entity->getParameter('color'); // 'red'
$entity->setParameters(['size' => 'L'], force: false); // recursive merge
```

### `ObjectTrackNullableTrait` / `ObjectTrackRequiredTrait`

Adds `objectClass` and `objectId` columns to track which object a record belongs to. Use the nullable variant when the relation is optional.

---

## Repository Traits

### `BaseRepositoryTrait`

Query/filter/pagination params are provided by [`letkode/query-filter-bundle`](https://github.com/letkode/query-filter-bundle).

```php
use Letkode\QueryFilterBundle\Request\FilterQueryRequest;

$repo->save($entity);
$repo->remove($entity);
$repo->findByUuid($uuid);           // returns T|null
$repo->findOrFailByUuid($uuid);     // throws Letkode\HttpExceptionBundle\Exception\EntityNotFoundException (404)
$repo->paginate($qb, $query, sortable: ['name'], searchable: ['name', 'email']);
// $query is a Letkode\QueryFilterBundle\Request\FilterQueryRequest
```

`sortable` and `searchable` entries may be a qualified alias path (`'p.lastName'`) instead of a bare
field name, to sort/search on a joined entity:

```php
$qb = $this->createQueryBuilder('c')->join('c.person', 'p');

$repo->paginate(
    $qb,
    $query,
    sortable:   ['createdAt', 'p.lastName'],
    searchable: ['p.firstName', 'p.lastName', 'p.email'],
);
```

A bare field name (no dot) still resolves against the root alias, exactly as before. The referenced
alias must already be joined on the `QueryBuilder` passed to `paginate()` — the bundle does not
validate or infer joins, same responsibility as `FilterInput::path`. Ordering on a path behind a
to-many join can duplicate rows in the paginated result (the usual Doctrine to-many fan-out); prefer
sorting/searching on a to-one relation, or on a column of the root entity, when possible.

#### Strict mode

`paginate()` validates the request against the declared allowlists before touching the database.
By default (`strict: true`) an undeclared sort field, an undeclared filter field, an unknown filter
operator or a malformed filter entry throws `Letkode\QueryFilterBundle\Exception\UndeclaredQueryParameterException`,
which carries the full list of rejections (`->rejections`) so the caller can report them all at once
and it is an HTTP status exception (`422`, with the errors by parameter), so the `ExceptionListener` of
`letkode/http-exception-bundle` renders it without any extra code. A `q` shorter than
`minSearchLength` is not a rejection — search is simply not applied.

```php
$repo->paginate($qb, $query, sortable: ['name'], strict: false); // legacy: silently ignore
```

### `TranslatableRepositoryTrait`

```php
$this->addTranslatedOrderBy($qb, 'p', 'name', $locale, 'ASC');
$this->addTranslatedSearch($qb, 'p', 'name', $searchTerm, $locale);
```

---

## Value Objects

All value objects are `final readonly`, normalize on construction and throw `ValueObjectException` on invalid input.

| Class | Validates |
|---|---|
| `Email` | Valid email, lowercased |
| `Phone` | E.164-compatible (strips spaces/dashes) |
| `Slug` | Lowercase, `[a-z0-9-]`, 2–255 chars |
| `Username` | `[a-zA-Z0-9_.-]`, 3–50 chars |

```php
$email = new Email('  USER@Example.COM  '); // 'user@example.com'
$slug  = new Slug('My Product Name');        // 'my-product-name'
```

---

## DQL Function

`TRANSLATE_FIELD_VALUE(column, 'field', :locale)` maps to `jsonb_extract_path_text(column, locale, field)`.

Useful for ordering and filtering on translated values stored in a jsonb `translations` column.

---

## Requirements

- PHP `^8.4`
- Symfony `^7.0 || ^8.0`
- `doctrine/orm` `^3.0`
- `doctrine/doctrine-bundle` `^3.0`
- `gedmo/doctrine-extensions` `^3.0`
- `letkode/http-exception-bundle` `^1.2`
- `letkode/query-filter-bundle` `^1.6`

---

## License

MIT — see [LICENSE](LICENSE).

## Property case

By default a filter/sort/search field name is used as the Doctrine property as-is. If your entities
use camelCase but your API exposes snake_case keys, declare the property spelling once:

```yaml
# config/packages/letkode_orm_toolkit.yaml
letkode_orm_toolkit:
    property_case: camel   # none (default) | camel | snake
```

`'legal_name' => FilterInput::text()` then filters on `alias.legalName`. An explicit
`FilterInput::text(path: 'co.legal_name')` is never converted.

To get a commented copy of the config in your project:

```bash
bin/console letkode:config:publish orm-toolkit
```

It writes `config/packages/letkode_orm_toolkit.yaml` and never overwrites an existing file unless you add `--force`. `--dry-run` shows what it would do.
