<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\Linq\Accumulator;
use PST\Core\Collections\Linq\Selector;
use PST\Core\Type;
use PST\Core\TypeHint;

trait LinqAggregationTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function aggregate(mixed $seed, Closure|Accumulator $accumulator, null|Closure|Selector $resultSelector = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorT = $thisEnumerator->TValue;
        $seedType = Type::ofInstance($seed) ?? TypeHint::mixed();
        $intType = Type::typeOf('int');

        if ($accumulator instanceof Closure) {
            $accumulator = Accumulator::of($accumulator);
        }

        $accumulatorParameterCount = count($accumulator->TParameters);

        if ($accumulatorParameterCount !== 2 && $accumulatorParameterCount !== 3) {
            throw new InvalidArgumentException("Accumulator must define exactly 2 or 3 parameters");
        }

        if (!$accumulator->TAccumulated->isAssignableFrom($seedType)) {
            throw new InvalidArgumentException("Seed type must be assignable to accumulator state parameter type");
        }

        if (!$accumulator->TItem->isAssignableFrom($thisEnumeratorT)) {
            throw new InvalidArgumentException("Accumulator item parameter type must be assignable from collection item type");
        }

        if ($accumulatorParameterCount === 3 && !$accumulator->TIndex->isAssignableFrom($intType)) {
            throw new InvalidArgumentException("Accumulator index parameter type must be assignable from int");
        }

        if (!$accumulator->TAccumulated->isAssignableFrom($accumulator->TReturn)) {
            throw new InvalidArgumentException("Accumulator return type must be assignable to accumulator state parameter type");
        }

        $accumulated = $seed;
        $index = 0;

        if (count($accumulator->TParameters) === 2) {
            foreach ($thisEnumerator as $item) {
                $accumulated = $accumulator($accumulated, $item);
                $index++;
            }
        } else {
            foreach ($thisEnumerator as $item) {
                $accumulated = $accumulator($accumulated, $item, $index);
                $index++;
            }
        }

        if ($resultSelector === null) {
            return $accumulated;
        }

        $accumulatorStateType = $accumulator->TAccumulated;

        if ($resultSelector instanceof Closure) {
            $reflectionFunction = new ReflectionFunction($resultSelector);
            $parameterCount = $reflectionFunction->getNumberOfParameters();

            if ($parameterCount !== 1) {
                throw new InvalidArgumentException("Result selector closure must accept exactly 1 parameter");
            }

            $resultSelectorParameterType = TypeHint::ofReflectionType($reflectionFunction->getParameters()[0]->getType());

            if (!$resultSelectorParameterType->isAssignableFrom($accumulatorStateType)) {
                throw new InvalidArgumentException("Result selector parameter type must be assignable from accumulator state type");
            }

            $resultSelectorClosure = $resultSelector;
            $resultSelector = new Selector(
                static fn (mixed $state, int $key): mixed => $resultSelectorClosure($state),
                null,
                $accumulatorStateType,
                $intType
            );
        }

        if (count($resultSelector->TParameters) !== 2) {
            throw new InvalidArgumentException("Result selector must define exactly 2 parameters (state, key)");
        } else if (!$resultSelector->TItem->isAssignableFrom($accumulatorStateType)) {
            throw new InvalidArgumentException("Result selector parameter type must be assignable from accumulator state type");
        } else if (!$resultSelector->TKey->isAssignableFrom($intType)) {
            throw new InvalidArgumentException("Result selector key type must be assignable from int");
        }

        return $resultSelector($accumulated, 0);
    }

    public function sum(null|Closure|Selector $selector = null): int|float {
        $thisEnumerator = $this->getEnumerator();
        $numericSelector = $this->normalizeNumericSelector($thisEnumerator, $selector);
        $sum = 0;

        foreach ($thisEnumerator as $key => $item) {
            $sum += $numericSelector !== null
                ? $numericSelector($item, $key)
                : $item;
        }

        return $sum;
    }

    public function average(null|Closure|Selector $selector = null): float {
        $thisEnumerator = $this->getEnumerator();
        $numericSelector = $this->normalizeNumericSelector($thisEnumerator, $selector);
        $sum = 0;
        $count = 0;

        foreach ($thisEnumerator as $key => $item) {
            $sum += $numericSelector !== null
                ? $numericSelector($item, $key)
                : $item;
            $count++;
        }

        if ($count === 0) {
            throw new InvalidArgumentException("Cannot compute the average of an empty sequence");
        }

        return $sum / $count;
    }

    public function min(null|Closure|Selector $selector = null): mixed {
        return $this->extreme($selector, selectMaximum: false);
    }

    public function max(null|Closure|Selector $selector = null): mixed {
        return $this->extreme($selector, selectMaximum: true);
    }
    
    private function normalizeNumericSelector(IEnumerator $thisEnumerator, null|Closure|Selector $selector): null|Selector {
        $numericType = TypeHint::union(TypeHint::int(), TypeHint::float());

        if ($selector === null) {
            if (!$numericType->isAssignableFrom($thisEnumerator->TValue)) {
                throw new InvalidArgumentException("Collection item type must be assignable to int|float");
            }

            return null;
        }

        if ($selector instanceof Closure) {
            $selector = new Selector($selector, null, $thisEnumerator->TValue, $thisEnumerator->TKey);
        }

        if (count($selector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        }

        if (!$selector->TItem->isAssignableFrom($thisEnumerator->TValue)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        }

        if (!$selector->TKey->isAssignableFrom($thisEnumerator->TKey)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        if (!$numericType->isAssignableFrom($selector->TReturn)) {
            throw new InvalidArgumentException("Selector return type must be assignable to int|float");
        }

        return $selector;
    }

    private function extreme(null|Closure|Selector $selector, bool $selectMaximum): mixed {
        $thisEnumerator = $this->getEnumerator();
        $comparableSelector = $this->normalizeComparableSelector($thisEnumerator, $selector);
        $hasValue = false;
        $extremeValue = null;

        foreach ($thisEnumerator as $key => $item) {
            $candidate = $comparableSelector !== null
                ? $comparableSelector($item, $key)
                : $item;

            if (!$hasValue) {
                $extremeValue = $candidate;
                $hasValue = true;
                continue;
            }

            $comparison = $this->compareAggregateValues($candidate, $extremeValue);

            if (($selectMaximum && $comparison > 0) || (!$selectMaximum && $comparison < 0)) {
                $extremeValue = $candidate;
            }
        }

        if (!$hasValue) {
            throw new InvalidArgumentException("Cannot compute an aggregate extreme value from an empty sequence");
        }

        return $extremeValue;
    }

    private function normalizeComparableSelector(IEnumerator $thisEnumerator, null|Closure|Selector $selector): null|Selector {
        if ($selector === null) {
            return null;
        }

        if ($selector instanceof Closure) {
            $selector = new Selector($selector, null, $thisEnumerator->TValue, $thisEnumerator->TKey);
        }

        if (count($selector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        }

        if (!$selector->TItem->isAssignableFrom($thisEnumerator->TValue)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        }

        if (!$selector->TKey->isAssignableFrom($thisEnumerator->TKey)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        return $selector;
    }

    private function compareAggregateValues(mixed $left, mixed $right): int {
        if ($left === $right) {
            return 0;
        }

        if (
            (is_int($left) || is_float($left) || is_string($left) || is_bool($left) || $left === null) &&
            (is_int($right) || is_float($right) || is_string($right) || is_bool($right) || $right === null)
        ) {
            return $left <=> $right;
        }

        if (is_object($left) && is_object($right) && method_exists($left, '__toString') && method_exists($right, '__toString')) {
            return (string)$left <=> (string)$right;
        }

        throw new InvalidArgumentException("Unable to compare aggregate values");
    }
}


