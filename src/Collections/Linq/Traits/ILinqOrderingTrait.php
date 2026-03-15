<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;

use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Comparer;
use PST\Core\Collections\Linq\Selector;

interface ILinqOrderingTrait {
    public function orderBy(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable;
    public function orderByDescending(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable;
    public function thenBy(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable;
    public function thenByDescending(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable;
}
