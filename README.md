# pst-core

`pst-core` provides shared low-level building blocks used by PST packages, including:

- collection abstractions (`IEnumerable`, `IEnumerator`, dictionary contracts)
- concrete collection implementations (`Enumerator`, `Dictionary`, `ReadOnlyDictionary`)
- LINQ-style operators for filtering, projection, ordering, grouping, and aggregation
- type helpers (`Type`, `TypeHint`)
- base object and exception primitives

## Requirements

- PHP 8.4+

## Installation

```bash
composer require jasonwt/pst-core
```

## Quick Example

```php
<?php

declare(strict_types=1);

use PST\Core\Collections\Enumerator;

$numbers = new Enumerator([1, 2, 3, 4, 5]);
$result = $numbers
    ->where(static fn (int $item): bool => $item % 2 === 0)
    ->select(static fn (int $item): int => $item * 10)
    ->toArray();

// [2 => 20, 4 => 40]
```

## Running Tests

```bash
composer test
```

## License

MIT. See [LICENSE](LICENSE).
