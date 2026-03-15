<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use Generator;

use InvalidArgumentException;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\Linq\Selector;

trait LinqGroupingTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function distinctBy(Closure|Selector $keySelector): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $selector = $this->normalizeGroupingSelector($thisEnumerator, $keySelector);

        $lazyGenerator = function() use ($thisEnumerator, $selector): Generator {
            $seenValues = [];

            foreach ($thisEnumerator as $key => $value) {
                $selectorValueKey = self::getSetValueKey($selector($value, $key));

                if (isset($seenValues[$selectorValueKey])) {
                    continue;
                }

                $seenValues[$selectorValueKey] = true;
                yield $key => $value;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumerator->TValue, $thisEnumerator->TKey);
    }

    public function union(iterable $second): IEnumerable {
        return $this->concat($second)->distinct();
    }

    public function intersect(iterable $second): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $secondLookup = $this->buildSetLookup($second);

        $lazyGenerator = function() use ($thisEnumerator, $secondLookup): Generator {
            $yieldedValues = [];

            foreach ($thisEnumerator as $key => $value) {
                $valueKey = self::getSetValueKey($value);

                if (!isset($secondLookup[$valueKey]) || isset($yieldedValues[$valueKey])) {
                    continue;
                }

                $yieldedValues[$valueKey] = true;
                yield $key => $value;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumerator->TValue, $thisEnumerator->TKey);
    }

    public function except(iterable $second): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $secondLookup = $this->buildSetLookup($second);

        $lazyGenerator = function() use ($thisEnumerator, $secondLookup): Generator {
            $yieldedValues = [];

            foreach ($thisEnumerator as $key => $value) {
                $valueKey = self::getSetValueKey($value);

                if (isset($secondLookup[$valueKey]) || isset($yieldedValues[$valueKey])) {
                    continue;
                }

                $yieldedValues[$valueKey] = true;
                yield $key => $value;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumerator->TValue, $thisEnumerator->TKey);
    }

    private function normalizeGroupingSelector(IEnumerator $thisEnumerator, Closure|Selector $keySelector): Selector {
        if ($keySelector instanceof Closure) {
            $keySelector = new Selector($keySelector, null, $thisEnumerator->TValue, $thisEnumerator->TKey);
        }

        if (count($keySelector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        }

        if (!$keySelector->TItem->isAssignableFrom($thisEnumerator->TValue)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        }

        if (!$keySelector->TKey->isAssignableFrom($thisEnumerator->TKey)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        return $keySelector;
    }

    private function buildSetLookup(iterable $source): array {
        $sourceEnumerator = $source instanceof IEnumerable
            ? $source->getEnumerator()
            : (new Enumerator($source))->getEnumerator();

        $lookup = [];

        foreach ($sourceEnumerator as $value) {
            $lookup[self::getSetValueKey($value)] = true;
        }

        return $lookup;
    }

    private static function getSetValueKey(mixed $value): string {
        if (is_object($value)) {
            return 'object:' . spl_object_id($value);
        }

        if (is_resource($value)) {
            return 'resource:' . get_resource_id($value);
        }

        return 'value:' . serialize($value);
    }
}
