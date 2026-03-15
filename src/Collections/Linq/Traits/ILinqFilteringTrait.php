<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;

use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Predicate;

interface ILinqFilteringTrait {
    // ─── Filtering ───────────────────────────────────────────────────────────
    public function where(Closure|Predicate $itemPredicate): IEnumerable;
}