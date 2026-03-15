<?php

declare(strict_types=1);

namespace PST\Core\Collections\Traits;

use Closure;
use Iterator;
use Generator;
use Traversable;
use IteratorAggregate;

use PST\Core\Type;
use PST\Core\TypeHint;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\IEnumerator;

use PST\Core\Collections\Linq\Traits\LinqTraits;

use InvalidArgumentException;

trait EnumeratorTrait {
    use LinqTraits;

    private Closure|Iterator $_enumerator;
    private bool $_disableTypeCheck = false;

    public readonly TypeHint $TKey;
    public readonly TypeHint $TValue;

    public mixed $current {
        get {
            $iterator = $this->resolveIteratorFromSource(
                $this->_enumerator instanceof Closure
                    ? ($this->_enumerator)()
                    : $this->_enumerator
            );

            return $iterator->current();
        }
    }

    private function __constructEnumerableTrait(null|Closure|iterable $items, null|TypeHint $TValue, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        if ($TValue !== null && $TValue->fullName === "null") {
            throw new InvalidArgumentException("Value type hint cannot be null.");
        }

        if ($TKey !== null && $TKey->fullName === "null") {
            throw new InvalidArgumentException("Key type hint cannot be null.");
        }

        if ($TKey !== null && !TypeHint::key()->isAssignableFrom($TKey)) {
            throw new InvalidArgumentException("Key type hint '{$TKey->fullName}' must be assignable to int|string.");
        }

        if ($items instanceof Closure) {
            $this->_enumerator = $items;
            $this->TKey = $TKey ?? TypeHint::key();
            $this->TValue = $TValue ?? TypeHint::undefined();

            $this->_disableTypeCheck = $disableTypeCheck !== null
                ? $disableTypeCheck
                : false;

        } else if ($items === null) {
            $this->TKey = $TKey ?? TypeHint::key();
            $this->TValue = $TValue ?? TypeHint::undefined();
            $this->_enumerator = static fn (): Generator => yield from [];

            $this->_disableTypeCheck = $disableTypeCheck !== null
                ? $disableTypeCheck
                : true;

        } else if ($items instanceof IEnumerable) {
            $TKey ??= $items->TKey;
            $TValue ??= $items->TValue;

            if (!$items->TKey->isAssignableFrom($TKey) || !$items->TValue->isAssignableFrom($TValue)) {
                throw new InvalidArgumentException(
                    "Type hints '{$TKey} => {$TValue}' are not compatible with source '{$items->TKey} => {$items->TValue}'."
                );
            }

            $this->_disableTypeCheck = $disableTypeCheck !== null
                ? $disableTypeCheck
                : true;

            $this->TKey = $TKey;
            $this->TValue = $TValue;

            $this->_enumerator = static fn () => $items->getEnumerator()->getIterator();

        } else {
            $this->TKey = $TKey ?? TypeHint::key();
            $this->TValue = $TValue ?? TypeHint::undefined();

            $this->_disableTypeCheck =  $disableTypeCheck !== null
                ? $disableTypeCheck
                : false;

            if (!$items instanceof Traversable) {
                $snapshot = $items;
                $this->_enumerator = static function() use ($snapshot): Generator {
                    foreach ($snapshot as $k => $v) {
                        yield $k => $v;
                    }
                };
            } else if ($items instanceof IteratorAggregate) {
                $this->_enumerator = static fn () => $items->getIterator();
            } else {
                // Non-aggregate iterators can be one-shot (for example Generator), so snapshot them.
                $snapshot = iterator_to_array($items, preserve_keys: true);
                $this->_enumerator = static function() use ($snapshot): Generator {
                    foreach ($snapshot as $k => $v) {
                        yield $k => $v;
                    }
                };
            }
        }
    }

    public function getIterator(): Iterator {
        $iteratorSource = $this->_enumerator instanceof Closure
            ? ($this->_enumerator)()
            : $this->_enumerator;

        $iterator = $this->resolveIteratorFromSource($iteratorSource);

        if ($this->_disableTypeCheck) {
            yield from $iterator;

        } else {
            foreach ($iterator as $k => $v) {
                if (!$this->TKey->isAssignableFrom(Type::ofInstance($k))) {
                    throw new InvalidArgumentException("All keys must be assignable to type {$this->TKey->fullName}");
                }

                if (!$this->TValue->isAssignableFrom(Type::ofInstance($v))) {
                    throw new InvalidArgumentException("All items must be assignable to type {$this->TValue->fullName}");
                }

                yield $k => $v;
            }
        }
    }

    public function getEnumerator(): IEnumerator {
        return $this;
    }

    private function resolveIteratorFromSource(mixed $source): Iterator {
        while ($source instanceof IteratorAggregate) {
            $source = $source->getIterator();
        }

        if ($source instanceof Iterator) {
            return $source;
        }

        if (!is_iterable($source)) {
            throw new InvalidArgumentException("Enumerator source must resolve to an iterable.");
        }

        return (function() use ($source): Generator {
            foreach ($source as $k => $v) {
                yield $k => $v;
            }
        })();
    }
}
