<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use InvalidArgumentException;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\Linq\Predicate;
use PST\Core\Exceptions\NotSupportedException;
use PST\Core\Type;

trait LinqElementAccessTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function first(null|Closure|Predicate $predicate = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        throw new InvalidArgumentException("No matching element found");
    }
 
    public function firstOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);
        $resolvedDefault = $this->resolveElementDefault($thisEnumerator, $default, func_num_args() >= 2);

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        return $resolvedDefault;
    }

    public function last(null|Closure|Predicate $predicate = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);

        $lastMatch = null;
        $hasMatch = false;

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $lastMatch = $item;
                $hasMatch = true;
            }
        }

        if (!$hasMatch) {
            throw new InvalidArgumentException("No matching element found");
        }

        return $lastMatch;
    }

    public function lastOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);
        $resolvedDefault = $this->resolveElementDefault($thisEnumerator, $default, func_num_args() >= 2);

        $lastMatch = $resolvedDefault;

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $lastMatch = $item;
            }
        }

        return $lastMatch;
    }

    public function single(null|Closure|Predicate $predicate = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);

        $found = false;
        $single = null;

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate !== null && !$predicate($item, $key)) {
                continue;
            }

            if ($found) {
                throw new InvalidArgumentException("More than one matching element found");
            }

            $single = $item;
            $found = true;
        }

        if (!$found) {
            throw new InvalidArgumentException("No matching element found");
        }

        return $single;
    }

    public function singleOrDefault(null|Closure|Predicate $predicate = null, mixed $default = null): mixed {
        $thisEnumerator = $this->getEnumerator();
        $predicate = $this->normalizeElementPredicate($thisEnumerator, $predicate);
        $resolvedDefault = $this->resolveElementDefault($thisEnumerator, $default, func_num_args() >= 2);

        $found = false;
        $single = $resolvedDefault;

        foreach ($thisEnumerator as $key => $item) {
            if ($predicate !== null && !$predicate($item, $key)) {
                continue;
            }

            if ($found) {
                throw new InvalidArgumentException("More than one matching element found");
            }

            $single = $item;
            $found = true;
        }

        return $single;
    }

    public function elementAt(int $index): mixed {
        if ($index < 0) {
            throw new InvalidArgumentException("Index cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $currentIndex = 0;

        foreach ($thisEnumerator as $item) {
            if ($currentIndex === $index) {
                return $item;
            }

            $currentIndex++;
        }

        throw new InvalidArgumentException("Index out of range");
    }

    public function elementAtOrDefault(int $index, mixed $default = null): mixed {
        if ($index < 0) {
            throw new InvalidArgumentException("Index cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $resolvedDefault = $this->resolveElementDefault($thisEnumerator, $default, func_num_args() >= 2);
        $currentIndex = 0;

        foreach ($thisEnumerator as $item) {
            if ($currentIndex === $index) {
                return $item;
            }

            $currentIndex++;
        }

        return $resolvedDefault;
    }

    private function normalizeElementPredicate(IEnumerator $thisEnumerator, null|Closure|Predicate $predicate): null|Predicate {
        if ($predicate === null) {
            return null;
        }

        if ($predicate instanceof Closure) {
            return new Predicate($predicate, $thisEnumerator->TValue, $thisEnumerator->TKey);
        }

        if (
            count($predicate->TParameters) !== 2 ||
            !$predicate->TItem->isAssignableFrom($thisEnumerator->TValue) ||
            !$predicate->TKey->isAssignableFrom($thisEnumerator->TKey)
        ) {
            throw new InvalidArgumentException("Predicate parameter type must be assignable from collection item type");
        }

        return $predicate;
    }

    private function resolveElementDefault(IEnumerator $thisEnumerator, mixed $default, bool $hasExplicitDefault): mixed {
        if (!$hasExplicitDefault) {
            return $thisEnumerator->TValue->default();
        }

        $defaultType = Type::ofInstance($default);

        if ($defaultType === null) {
            throw new InvalidArgumentException("Default value type is not supported by the type system");
        }

        if (!$thisEnumerator->TValue->isAssignableFrom($defaultType)) {
            try {
                if ($default === $thisEnumerator->TValue->default()) {
                    return $default;
                }
            } catch (NotSupportedException) {
            }

            throw new InvalidArgumentException(
                "Default value type '{$defaultType->fullName}' is not assignable to collection item type '{$thisEnumerator->TValue->fullName}'"
            );
        }

        return $default;
    }
}
