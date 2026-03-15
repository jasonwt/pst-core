<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use PST\Core\Collections\Linq\Accumulator;
use PST\Core\Collections\Linq\Selector;

interface ILinqAggregationTrait {
    public function aggregate(mixed $seed, Closure|Accumulator $accumulator, null|Closure|Selector $resultSelector = null): mixed;
    public function sum(null|Closure|Selector $selector = null): int|float;
    public function average(null|Closure|Selector $selector = null): float;
    public function min(null|Closure|Selector $selector = null): mixed;
    public function max(null|Closure|Selector $selector = null): mixed;
}
