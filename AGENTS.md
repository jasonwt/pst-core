# AGENTS.md for `pst-core`

This guide helps LLM agents (and new contributors) work safely in `pst-core`.

## Package Purpose

`pst-core` is the shared foundation for PST packages:

- runtime type model: `Type`, `TypeHint`
- base object behavior: `CoreObject`, `CoreObjectTrait`
- collection abstractions and implementations
- LINQ-style operations layered over enumerables
- common exception hierarchy

Keep this package small, predictable, and backward-compatible.

## Source Map

- `src/Type.php`, `src/TypeHint.php`
  - runtime type inspection, assignability, defaults, and hint composition
- `src/CoreObject.php`, `src/CoreObjectTrait.php`
  - base behavior (`getType()`, `getHashCode()`, `__toString()`)
- `src/Collections/*`
  - `IEnumerable`, `IEnumerator`, dictionary interfaces and concrete classes
- `src/Collections/Traits/*`
  - shared implementation for enumerator and dictionary behavior
- `src/Collections/Linq/*`
  - delegates (`Predicate`, `Selector`, `Comparer`, `Accumulator`)
  - operator traits (`Linq*Trait.php`)
- `src/Exceptions/*`
  - PST exception model

## Non-Negotiable Invariants

1. Keep runtime generic/type safety intact.
2. Preserve deterministic iteration order.
3. Keep `TypeHint::isAssignableFrom()` and `isAssignableTo()` symmetric/consistent.
4. Do not weaken explicit default value checks in LINQ `*OrDefault` methods.
5. Dictionary key types must remain assignable to `int|string`.
6. Read-only collections must not allow mutation through `ArrayAccess`.

## LINQ Semantics Notes

- `firstOrDefault`, `lastOrDefault`, `singleOrDefault`, `elementAtOrDefault`:
  - if default is explicitly provided, it must be assignable to `TValue`
  - if not provided, default is resolved from `TValue->default()`
- `first/last/single` throw when no match exists
- `single/singleOrDefault` enforce cardinality (fail when more than one match)

## Collection Implementation Notes

- `EnumeratorTrait` supports lazy and eager sources
  - one-shot iterators are snapshotted to remain re-iterable
- `ReadOnlyDictionaryTrait` stores internal `_items` and exposes:
  - `count`, `keys`, `values`
  - key-validated `offsetExists/offsetGet`
  - `containsKey`, `containsValue`, `tryGetValue`, `empty`

## Core Object Notes

- `getHashCode()` uses `KeyedValueSequencer` with `WeakMap`
- hash values are process-local identity markers, not persistent IDs

## Common Commands

Run from `pst-core`:

```bash
composer validate --strict
composer test
```

Targeted test run:

```bash
php vendor/bin/phpunit tests/Functionality/EnumerableLinqAdvancedTest.php
```

Quick syntax check:

```bash
php -l src/Collections/Linq/Traits/LinqElementAccessTrait.php
```

## Safe Change Workflow

1. Read the relevant interface and trait together before editing.
2. Make the smallest change that preserves public behavior.
3. Add/adjust tests in `tests/Functionality` first for bug fixes.
4. Run full package tests (`composer test`).
5. Re-check `composer validate --strict`.

## If You Add or Change LINQ Operators

1. Update interface contract in `src/Collections/Linq/Traits/I*.php` when needed.
2. Update implementation trait in matching `Linq*.php`.
3. Keep type checks aligned with `TKey`/`TValue`.
4. Add functionality tests plus edge-case tests:
  - empty source
  - invalid default type
  - predicate type mismatch
  - ordering/cardinality behavior

## Backward Compatibility Rules

- Do not rename public APIs casually.
- Do not change exception types/messages without test updates and release notes.
- Prefer additive changes over breaking changes.
- Treat `src/TypeHint.php` and collection interfaces as high-risk BC surfaces.

## Publishing Checklist (Package)

1. `composer validate --strict` passes
2. `composer test` passes
3. `README.md` updated for user-facing API changes
4. version tag prepared (`vX.Y.Z`)
5. push tag for Packagist update

