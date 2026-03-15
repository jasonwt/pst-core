<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;

use PST\Core\Collections\Linq\Predicate;

interface ILinqQuantifiersTrait {
    public function contains(mixed $value): bool;
    public function any(null|Closure|Predicate $predicate = null): bool;
    public function all(Closure|Predicate $predicate): bool;
    public function count(null|Closure|Predicate $predicate = null): int;
    public function sequenceEqual(iterable $second): bool;
}
