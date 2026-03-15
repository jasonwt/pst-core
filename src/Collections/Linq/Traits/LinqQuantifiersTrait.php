<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use InvalidArgumentException;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\Linq\Predicate;

trait LinqQuantifiersTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function contains(mixed $value): bool {
        foreach ($this->getEnumerator() as $item) {
            if ($item === $value) {
                return true;
            }
        }

        return false;
    }

    public function any(null|Closure|Predicate $predicate = null): bool {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($predicate !== null) {
            if ($predicate instanceof Closure) {
                $predicate = new Predicate($predicate, $thisEnumeratorTValue, $thisEnumeratorTKey);
            } else if (
                count($predicate->TParameters) !== 2 ||
                !$predicate->TItem->isAssignableFrom($thisEnumeratorTValue) ||
                !$predicate->TKey->isAssignableFrom($thisEnumeratorTKey)
            ) {
                throw new InvalidArgumentException("Predicate parameter type must be assignable from collection item type");
            }
        }

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return true;
            }
        }

        return false;
    }

    public function all(Closure|Predicate $predicate): bool {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($predicate instanceof Closure) {
            $predicate = new Predicate($predicate, $thisEnumeratorTValue, $thisEnumeratorTKey);
        } else if (
            count($predicate->TParameters) !== 2 ||
            !$predicate->TItem->isAssignableFrom($thisEnumeratorTValue) ||
            !$predicate->TKey->isAssignableFrom($thisEnumeratorTKey)
        ) {
            throw new InvalidArgumentException("Predicate parameter type must be assignable from collection item type");
        }

        foreach ($thisEnumerator as $key => $item) {
            if (!$predicate($item, $key)) {
                return false;
            }
        }

        return true;
    }

    public function count(null|Closure|Predicate $predicate = null): int {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($predicate !== null) {
            if ($predicate instanceof Closure) {
                $predicate = new Predicate($predicate, $thisEnumeratorTValue, $thisEnumeratorTKey);
            } else if (
                count($predicate->TParameters) !== 2 ||
                !$predicate->TItem->isAssignableFrom($thisEnumeratorTValue) ||
                !$predicate->TKey->isAssignableFrom($thisEnumeratorTKey)
            ) {
                throw new InvalidArgumentException("Predicate parameter type must be assignable from collection item type");
            }
        }

        $count = 0;
        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $count++;
            }
        }

        return $count;
    }

    public function sequenceEqual(iterable $second): bool {
        $secondIterator = (new Enumerator($second))->getIterator();
        $secondIterator->rewind();

        foreach ($this->getEnumerator() as $firstKey => $firstValue) {
            if (!$secondIterator->valid()) {
                return false;
            }

            if ($firstKey !== $secondIterator->key() || $firstValue !== $secondIterator->current()) {
                return false;
            }

            $secondIterator->next();
        }

        return !$secondIterator->valid();
    }
}


