<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use Generator;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Predicate;

use InvalidArgumentException;

trait LinqFilteringTrait {
    public abstract function getEnumerator(): IEnumerator;

    // ─── Filtering ───────────────────────────────────────────────────────────
    public function where(Closure|Predicate $itemPredicate): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($itemPredicate instanceof Closure) {
            $itemPredicate = new Predicate($itemPredicate, $thisEnumeratorTValue, $thisEnumeratorTKey);
        }

        if (count($itemPredicate->TParameters) !== 2) {
            throw new InvalidArgumentException("Predicate must have exactly 2 parameters (item, key)");
        } else if (!$itemPredicate->TItem->isAssignableFrom($thisEnumeratorTValue)) {
            throw new InvalidArgumentException("Predicate parameter type must be assignable from collection item type");
        } else if (!$itemPredicate->TKey->isAssignableFrom($thisEnumeratorTKey)) {
            throw new InvalidArgumentException("Predicate key type must be assignable from collection key type");
        }

        $lazyGenerator = function() use ($itemPredicate, $thisEnumerator): Generator {
            foreach ($thisEnumerator as $k => $v) {
                if ($itemPredicate($v, $k)) {
                    yield $k => $v;
                }
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
}


