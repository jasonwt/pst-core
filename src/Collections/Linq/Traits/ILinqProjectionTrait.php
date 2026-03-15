<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;

use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Selector;

interface ILinqProjectionTrait {
    public function distinct(): IEnumerable;
    public function reverse(): IEnumerable;
    public function select(Closure|Selector $selector): IEnumerable;
    public function selectMany(Closure|Selector $selector): IEnumerable;
}
