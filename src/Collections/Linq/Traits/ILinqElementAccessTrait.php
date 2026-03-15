<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use PST\Core\Collections\Linq\Predicate;

interface ILinqElementAccessTrait {
    public function first(null|Closure|Predicate $predicate = null): mixed;
    public function firstOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed;
    public function last(null|Closure|Predicate $predicate = null): mixed;
    public function lastOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed;
    public function single(null|Closure|Predicate $predicate = null): mixed;
    public function singleOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed;
    public function elementAt(int $index): mixed;
    public function elementAtOrDefault(int $index, mixed $default = null): mixed;
}
