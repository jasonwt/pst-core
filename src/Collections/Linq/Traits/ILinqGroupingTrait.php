<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;

use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Selector;

interface ILinqGroupingTrait {
    public function distinctBy(Closure|Selector $keySelector): IEnumerable;
    public function union(iterable $second): IEnumerable;
    public function intersect(iterable $second): IEnumerable;
    public function except(iterable $second): IEnumerable;
}
