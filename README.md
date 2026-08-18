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

### `HasTranslationsTrait`

Adds a `translations` jsonb column for multi-locale field values.

```php
$entity->setTranslation('es', 'name', 'Producto');
$entity->getTranslation('es', 'name'); // 'Producto'
```

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
$repo->findOrFailByUuid($uuid);     // throws EntityNotFoundException
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
- `doctrine/bundle` `^2.0`
- `gedmo/doctrine-extensions` `^3.0`
- `letkode/common-bundle` `^1.0`
- `letkode/query-filter-bundle` `^1.0`

---

## License

MIT — see [LICENSE](LICENSE).
