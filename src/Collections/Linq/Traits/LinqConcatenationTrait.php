<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Generator;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\IEnumerator;
use PST\Core\Type;
use PST\Core\TypeHint;

trait LinqConcatenationTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function concat(iterable $second): IEnumerable {
        $firstEnumerator = $this->getEnumerator();
        $secondEnumerator = $second instanceof IEnumerable
            ? $second->getEnumerator()
            : (new Enumerator($second))->getEnumerator();

        $lazyGenerator = function() use ($firstEnumerator, $secondEnumerator): Generator {
            foreach ($firstEnumerator as $key => $value) {
                yield $key => $value;
            }

            foreach ($secondEnumerator as $key => $value) {
                yield $key => $value;
            }
        };

        return new Enumerator(
            $lazyGenerator(),
            $this->combineConcatenatedTypeHints($firstEnumerator->TValue, $secondEnumerator->TValue, TypeHint::undefined()),
            $this->combineConcatenatedTypeHints($firstEnumerator->TKey, $secondEnumerator->TKey, TypeHint::key())
        );
    }

    public function append(mixed $element): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $elementType = $this->resolveValueTypeHint($element);
        $appendedKey = $this->getNextSyntheticKey($thisEnumerator);

        $lazyGenerator = function() use ($thisEnumerator, $appendedKey, $element): Generator {
            foreach ($thisEnumerator as $key => $value) {
                yield $key => $value;
            }

            yield $appendedKey => $element;
        };

        return new Enumerator(
            $lazyGenerator(),
            $this->combineConcatenatedTypeHints($thisEnumerator->TValue, $elementType, TypeHint::undefined()),
            $this->combineConcatenatedTypeHints($thisEnumerator->TKey, Type::typeOf('int'), TypeHint::key())
        );
    }

    public function prepend(mixed $element): IEnumerable {
        $thisEnumerator = $this->getEnumerator();
        $elementType = $this->resolveValueTypeHint($element);
        $prependedKey = $this->getPreviousSyntheticKey($thisEnumerator);

        $lazyGenerator = function() use ($thisEnumerator, $prependedKey, $element): Generator {
            yield $prependedKey => $element;

            foreach ($thisEnumerator as $key => $value) {
                yield $key => $value;
            }
        };

        return new Enumerator(
            $lazyGenerator(),
            $this->combineConcatenatedTypeHints($thisEnumerator->TValue, $elementType, TypeHint::undefined()),
            $this->combineConcatenatedTypeHints($thisEnumerator->TKey, Type::typeOf('int'), TypeHint::key())
        );
    }

    public function zip(iterable $second): IEnumerable {
        $firstEnumerator = $this->getEnumerator();
        $secondEnumerator = $second instanceof IEnumerable
            ? $second->getEnumerator()
            : (new Enumerator($second))->getEnumerator();

        $lazyGenerator = function() use ($firstEnumerator, $secondEnumerator): Generator {
            $secondIterator = $secondEnumerator->getIterator();
            $secondIterator->rewind();

            foreach ($firstEnumerator as $firstKey => $firstValue) {
                if (!$secondIterator->valid()) {
                    break;
                }

                yield $firstKey => [$firstValue, $secondIterator->current()];
                $secondIterator->next();
            }
        };

        return new Enumerator($lazyGenerator(), TypeHint::array(), $firstEnumerator->TKey);
    }

    private function combineConcatenatedTypeHints(TypeHint $left, TypeHint $right, TypeHint $unknownFallback): TypeHint {
        if ($left->fullName === $right->fullName) {
            return $left;
        }

        if ($left->fullName === 'undefined' || $right->fullName === 'undefined') {
            return $unknownFallback;
        }

        if ($left->isAssignableFrom($right)) {
            return $left;
        }

        if ($right->isAssignableFrom($left)) {
            return $right;
        }

        return TypeHint::union($left, $right);
    }

    private function resolveValueTypeHint(mixed $value): TypeHint {
        return Type::ofInstance($value) ?? TypeHint::undefined();
    }

    private function getNextSyntheticKey(IEnumerable $source): int {
        $maxNumericKey = null;

        foreach ($source as $key => $value) {
            if (is_int($key)) {
                $maxNumericKey = $maxNumericKey === null
                    ? $key
                    : max($maxNumericKey, $key);
            }
        }

        return $maxNumericKey === null
            ? 0
            : $maxNumericKey + 1;
    }

    private function getPreviousSyntheticKey(IEnumerable $source): int {
        $minNumericKey = null;

        foreach ($source as $key => $value) {
            if (is_int($key)) {
                $minNumericKey = $minNumericKey === null
                    ? $key
                    : min($minNumericKey, $key);
            }
        }

        return $minNumericKey === null
            ? 0
            : $minNumericKey - 1;
    }
}
