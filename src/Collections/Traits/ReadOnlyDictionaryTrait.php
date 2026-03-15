<?php

declare(strict_types=1);

namespace PST\Core\Collections\Traits;

use Generator;

use PST\Core\Type;
use PST\Core\TypeHint;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\IEnumerable;

use PST\Core\Exceptions\KeyNotFoundException;

use InvalidArgumentException;

trait ReadOnlyDictionaryTrait {
    use EnumeratorTrait;

    private array $_items = [];    

    public int $count {get => count($this->_items);}
    public array $keys {get => array_keys($this->_items);}
    public array $values {get => array_values($this->_items);}

    public function __construct(null|iterable $items = null, null|TypeHint $TValue = null, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        $this->_items = is_array($items ?? []) 
            ? ($items ?? []) 
            : iterator_to_array($items);

        $generator = function(): Generator {
            foreach ($this->_items as $key => $value) {
                yield $key => $value;
            }
        };

        if ($TValue === null || $TValue->fullName === "null") {
            $TValue = null;
        }

        if ($TKey === null || $TKey->fullName === "null") {
            $TKey = null;
        } else if (!TypeHint::key()->isAssignableFrom($TKey)) {
            throw new InvalidArgumentException("Key type hint '{$TKey}' must be assignable to int|string.");
        }

        if ($items instanceof IEnumerable) {
            $TKey ??= $items->TKey;
            $TValue ??= $items->TValue;

            if (!$TKey->isAssignableFrom($items->TKey) || !$TValue->isAssignableFrom($items->TValue)) {
                throw new InvalidArgumentException(
                    "Type hints '{$TKey} => {$TValue}' are not compatible with source '{$items->TKey} => {$items->TValue}'."
                );
            }

            $disableTypeCheck = $disableTypeCheck !== null
                ? $disableTypeCheck
                : true;
        } else {
            $TKey ??= TypeHint::key();
            $TValue ??= TypeHint::undefined();

            $disableTypeCheck = $disableTypeCheck !== null
                ? $disableTypeCheck
                : ($items === null);
        }

        if (!$disableTypeCheck) {
            foreach ($this->_items as $key => $value) {
                if (!$TKey->isAssignableFrom(Type::ofInstance($key))) {
                    throw new InvalidArgumentException(
                        "Key type '" . Type::ofInstance($key) . "' is not assignable to key type '{$TKey}'."
                    );
                }

                if (!$TValue->isAssignableFrom(Type::ofInstance($value))) {
                    throw new InvalidArgumentException(
                        "Value type '" . Type::ofInstance($value) . "' is not assignable to value type '{$TValue}'."
                    );
                }
            }
        }

        $this->__constructEnumerableTrait($generator, $TValue, $TKey, $disableTypeCheck);
    }

    public function offsetExists(mixed $offset): bool {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($offset))) {
            throw new InvalidArgumentException(
                "Key type '" . Type::ofInstance($offset) . "' is not assignable to key type '{$this->TKey}'."
            );
        }

        return array_key_exists($offset, $this->_items);
    }

    public function offsetGet(mixed $offset): mixed {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($offset))) {
            throw new InvalidArgumentException(
                "Key type '" . Type::ofInstance($offset) . "' is not assignable to key type '{$this->TKey}'."
            );
        }

        return array_key_exists($offset, $this->_items)
            ? $this->_items[$offset]
            : throw new KeyNotFoundException("Key '{$offset}' not found.", $offset);
    }

    public abstract function offsetSet(mixed $offset, mixed $value): void;
    public abstract function offsetUnset(mixed $offset): void;

    public function getEnumerator(): IEnumerator {
        return $this;
    }

    public function containsKey(int|string $key): bool {
        return $this->offsetExists($key);
    }

    public function containsValue(mixed $value): bool {
        foreach ($this->_items as $item) {
            if ($item === $value) {
                return true;
            }
        }

        return false;
    }

    public function empty(): bool {
        return empty($this->_items);
    }

    public function tryGetValue($key, &$value): bool {
        if (!$this->offsetExists($key)) {
            $value = null;
            return false;
        }

        $value = $this->_items[$key];
        return true;
    }    
}
