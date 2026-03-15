<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use Generator;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Selector;

use InvalidArgumentException;

trait LinqProjectionTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function distinct(): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($thisEnumerator): Generator {
            $seenValues = [];

            foreach ($thisEnumerator as $key => $value) {
                $distinctKey = self::getDistinctValueKey($value);

                if (isset($seenValues[$distinctKey])) {
                    continue;
                }

                $seenValues[$distinctKey] = true;
                yield $key => $value;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }

    public function reverse(): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($thisEnumerator): Generator {
            $items = [];

            foreach ($thisEnumerator as $key => $value) {
                $items[] = [$key, $value];
            }

            for ($i = count($items) - 1; $i >= 0; $i--) {
                yield $items[$i][0] => $items[$i][1];
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }

    public function select(Closure|Selector $selector): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($selector instanceof Closure) {
            $selector = new Selector($selector, null, $thisEnumeratorTValue, $thisEnumeratorTKey);
        }

        if (count($selector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        } else if (!$selector->TItem->isAssignableFrom($thisEnumeratorTValue)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        } else if (!$selector->TKey->isAssignableFrom($thisEnumeratorTKey)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        $lazyGenerator = function() use ($selector, $thisEnumerator): Generator {
            foreach ($thisEnumerator as $k => $v) {
                yield $k => $selector($v, $k);
            }
        };

        return new Enumerator($lazyGenerator(), $selector->TReturn, $thisEnumeratorTKey);
    }

    public function selectMany(Closure|Selector $selector): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        if ($selector instanceof Closure) {
            $selector = new Selector($selector, null, $thisEnumeratorTValue, $thisEnumeratorTKey);
        }

        if (count($selector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        } else if (!$selector->TItem->isAssignableFrom($thisEnumeratorTValue)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        } else if (!$selector->TKey->isAssignableFrom($thisEnumeratorTKey)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        $lazyGenerator = function() use ($selector, $thisEnumerator): Generator {
            $seenProjectedKeys = [];

            foreach ($thisEnumerator as $k => $v) {
                $projected = $selector($v, $k);

                if (!is_iterable($projected)) {
                    throw new InvalidArgumentException("selectMany selector must return an iterable");
                }

                foreach ($projected as $projectedKey => $subValue) {
                    if (
                        (is_int($projectedKey) || is_string($projectedKey)) &&
                        !array_key_exists($projectedKey, $seenProjectedKeys)
                    ) {
                        $seenProjectedKeys[$projectedKey] = true;
                        yield $projectedKey => $subValue;
                        continue;
                    }

                    yield $subValue;
                }
            }
        };

        return new Enumerator($lazyGenerator());
    }

    private static function getDistinctValueKey(mixed $value): string {
        if (is_object($value)) {
            return 'object:' . spl_object_id($value);
        }

        if (is_resource($value)) {
            return 'resource:' . get_resource_id($value);
        }

        return 'value:' . serialize($value);
    }
}
