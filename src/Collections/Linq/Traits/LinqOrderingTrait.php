<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Comparer;
use PST\Core\Collections\Linq\Selector;
use PST\Core\Collections\Linq\Traits\Support\OrderedEnumerable;


trait LinqOrderingTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function orderBy(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable {
        return OrderedEnumerable::create($this->getEnumerator(), $keySelector, $comparer, false);
    }

    public function orderByDescending(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable {
        return OrderedEnumerable::create($this->getEnumerator(), $keySelector, $comparer, true);
    }

    public function thenBy(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable {
        if ($this instanceof OrderedEnumerable) {
            return $this->appendOrdering($keySelector, $comparer, false);
        }

        return OrderedEnumerable::create($this->getEnumerator(), $keySelector, $comparer, false);
    }

    public function thenByDescending(Closure|Selector $keySelector, null|Closure|Comparer $comparer = null): IEnumerable {
        if ($this instanceof OrderedEnumerable) {
            return $this->appendOrdering($keySelector, $comparer, true);
        }

        return OrderedEnumerable::create($this->getEnumerator(), $keySelector, $comparer, true);
    }
}