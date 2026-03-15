<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits\Support;

use Closure;
use Generator;
use Traversable;
use IteratorAggregate;

use PST\Core\TypeHint;
use PST\Core\CoreObject;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Comparer;
use PST\Core\Collections\Linq\Selector;
use PST\Core\Collections\Linq\Traits\LinqTraits;

use InvalidArgumentException;

final class OrderedEnumerable extends CoreObject implements IEnumerable, IteratorAggregate {
    use LinqTraits;

    private IEnumerable $_source;
    private array $_criteria = [];

    public readonly TypeHint $TKey;
    public readonly TypeHint $TValue;
    public TypeHint $T {get => $this->TValue;}

    public function __construct(IEnumerable $source, array $criteria) {
        if (count($criteria) < 1) {
            throw new InvalidArgumentException("At least one ordering criterion is required.");
        }

        $this->_source = $source;
        $this->_criteria = $criteria;
        $this->TKey = $source->TKey;
        $this->TValue = $source->TValue;

        parent::__construct();
    }

    public static function create(
        IEnumerable $source,
        Closure|Selector $keySelector,
        null|Closure|Comparer $comparer = null,
        bool $descending = false
    ): self {
        $selector = self::normalizeSelector($source->TValue, $source->TKey, $keySelector);
        $normalizedComparer = self::normalizeComparer($comparer);

        return new self($source, [[
            'selector' => $selector,
            'comparer' => $normalizedComparer,
            'descending' => $descending,
        ]]);
    }

    public function appendOrdering(
        Closure|Selector $keySelector,
        null|Closure|Comparer $comparer = null,
        bool $descending = false
    ): self {
        $selector = self::normalizeSelector($this->_source->TValue, $this->_source->TKey, $keySelector);
        $normalizedComparer = self::normalizeComparer($comparer);

        $criteria = $this->_criteria;
        $criteria[] = [
            'selector' => $selector,
            'comparer' => $normalizedComparer,
            'descending' => $descending,
        ];

        return new self($this->_source, $criteria);
    }

    public function getEnumerator(): Enumerator {
        $sourceEnumerator = $this->_source->getEnumerator();

        $sortedGenerator = function() use ($sourceEnumerator): Generator {
            $rows = [];
            $index = 0;

            foreach ($sourceEnumerator as $key => $value) {
                $orderKeys = [];

                foreach ($this->_criteria as $criterion) {
                    $orderKeys[] = $criterion['selector']($value, $key);
                }

                $rows[] = [
                    'key' => $key,
                    'value' => $value,
                    'index' => $index,
                    'orderKeys' => $orderKeys,
                ];

                $index++;
            }

            usort($rows, function(array $left, array $right): int {
                foreach ($this->_criteria as $i => $criterion) {
                    $comparison = $this->compareValues(
                        $left['orderKeys'][$i],
                        $right['orderKeys'][$i],
                        $criterion['comparer']
                    );

                    if ($comparison !== 0) {
                        return $criterion['descending'] ? -$comparison : $comparison;
                    }
                }

                // Keep ordering stable when all criteria compare equal.
                return $left['index'] <=> $right['index'];
            });

            foreach ($rows as $row) {
                yield $row['key'] => $row['value'];
            }
        };

        return new Enumerator($sortedGenerator(), $this->TValue, $this->TKey);
    }

    public function getIterator(): Traversable {
        return $this->getEnumerator()->getIterator();
    }

    private static function normalizeSelector(TypeHint $sourceType, TypeHint $sourceKeyType, Closure|Selector $keySelector): Selector {
        if ($keySelector instanceof Closure) {
            $keySelector = new Selector($keySelector, null, $sourceType, $sourceKeyType);
        }

        if (count($keySelector->TParameters) !== 2) {
            throw new InvalidArgumentException("Selector must have exactly 2 parameters (item, key)");
        } else if (!$keySelector->TItem->isAssignableFrom($sourceType)) {
            throw new InvalidArgumentException("Selector parameter type must be assignable from collection item type");
        } else if (!$keySelector->TKey->isAssignableFrom($sourceKeyType)) {
            throw new InvalidArgumentException("Selector key type must be assignable from collection key type");
        }

        return $keySelector;
    }

    private static function normalizeComparer(null|Closure|Comparer $comparer): null|Comparer {
        if ($comparer === null) {
            return null;
        }

        if ($comparer instanceof Closure) {
            return new Comparer($comparer);
        }

        if (count($comparer->TParameters) !== 2) {
            throw new InvalidArgumentException("Comparer must define exactly two parameters");
        }

        $intType = Type::typeOf('int');
        if (!$comparer->TReturn->isAssignableTo($intType)) {
            throw new InvalidArgumentException("Comparer return type must be assignable to int");
        }

        return $comparer;
    }

    private function compareValues(mixed $left, mixed $right, null|Comparer $comparer): int {
        if ($comparer !== null) {
            return $comparer($left, $right);
        }

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

        throw new InvalidArgumentException("Unable to compare ordering keys without a comparer");
    }
}
