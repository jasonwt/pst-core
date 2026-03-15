<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use Generator;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Predicate;
use PST\Core\TypeHint;

use InvalidArgumentException;

trait LinqPartitioningTrait {
    public abstract function getEnumerator(): IEnumerator;

    // ─── Partitioning ────────────────────────────────────────────────────────
    public function skip(int $count): IEnumerable {
        if ($count < 0) {
            throw new InvalidArgumentException("Count cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($count, $thisEnumerator): Generator {
            $skipped = 0;
            foreach ($thisEnumerator as $k => $v) {
                if ($skipped < $count) {
                    $skipped++;
                    continue;
                }
                yield $k => $v;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function skipWhile(Closure|Predicate $predicate): IEnumerable {
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

        $lazyGenerator = function() use ($predicate, $thisEnumerator): Generator {
            $skipping = true;
            foreach ($thisEnumerator as $k => $v) {
                if ($skipping && !$predicate($v, $k)) {
                    $skipping = false;
                }
                if (!$skipping) {
                    yield $k => $v;
                }
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function skipUntil(Closure|Predicate $predicate): IEnumerable {
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

        $lazyGenerator = function() use ($predicate, $thisEnumerator): Generator {
            $skipping = true;
            foreach ($thisEnumerator as $k => $v) {
                if ($skipping && $predicate($v, $k)) {
                    $skipping = false;
                }
                if (!$skipping) {
                    yield $k => $v;
                }
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function skipLast(int $count): IEnumerable {
        if ($count < 0) {
            throw new InvalidArgumentException("Count cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($count, $thisEnumerator): Generator {
            $buffer = [];
            foreach ($thisEnumerator as $k => $v) {
                $buffer[] = [$k, $v];
                if (count($buffer) > $count) {
                    [$yieldK, $yieldV] = array_shift($buffer);
                    yield $yieldK => $yieldV;
                }
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function take(int $count): IEnumerable {
        if ($count < 0) {
            throw new InvalidArgumentException("Count cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($count, $thisEnumerator): Generator {
            $taken = 0;
            foreach ($thisEnumerator as $k => $v) {
                if ($taken >= $count) {
                    break;
                }
                yield $k => $v;
                $taken++;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function takeWhile(Closure|Predicate $predicate): IEnumerable {
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

        $lazyGenerator = function() use ($predicate, $thisEnumerator): Generator {
            foreach ($thisEnumerator as $k => $v) {
                if (!$predicate($v, $k)) {
                    break;
                }
                yield $k => $v;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function takeUntil(Closure|Predicate $predicate): IEnumerable {
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

        $lazyGenerator = function() use ($predicate, $thisEnumerator): Generator {
            foreach ($thisEnumerator as $k => $v) {
                if ($predicate($v, $k)) {
                    break;
                }
                yield $k => $v;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }
    public function takeLast(int $count): IEnumerable {
        if ($count < 0) {
            throw new InvalidArgumentException("Count cannot be negative");
        }

        $thisEnumerator = $this->getEnumerator();
        $thisEnumeratorTValue = $thisEnumerator->TValue;
        $thisEnumeratorTKey = $thisEnumerator->TKey;

        $lazyGenerator = function() use ($count, $thisEnumerator): Generator {
            $buffer = [];
            foreach ($thisEnumerator as $k => $v) {
                $buffer[] = [$k, $v];
                if (count($buffer) > $count) {
                    array_shift($buffer);
                }
            }
            foreach ($buffer as [$yieldK, $yieldV]) {
                yield $yieldK => $yieldV;
            }
        };

        return new Enumerator($lazyGenerator(), $thisEnumeratorTValue, $thisEnumeratorTKey);
    }

    public function chunk(int $size): IEnumerable {
        if ($size <= 0) {
            throw new InvalidArgumentException("Chunk size must be greater than zero");
        }

        $thisEnumerator = $this->getEnumerator();

        $lazyGenerator = function() use ($size, $thisEnumerator): Generator {
            $chunk = [];
            $chunkIndex = 0;

            foreach ($thisEnumerator as $key => $value) {
                if ((is_int($key) || is_string($key)) && !array_key_exists($key, $chunk)) {
                    $chunk[$key] = $value;
                } else {
                    $chunk[] = $value;
                }

                if (count($chunk) === $size) {
                    yield $chunkIndex => $chunk;
                    $chunk = [];
                    $chunkIndex++;
                }
            }

            if ($chunk !== []) {
                yield $chunkIndex => $chunk;
            }
        };

        return new Enumerator($lazyGenerator(), TypeHint::array(), TypeHint::int());
    }
}


