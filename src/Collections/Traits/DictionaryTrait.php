<?php

declare(strict_types=1);

namespace PST\Core\Collections\Traits;

use PST\Core\Type;
use PST\Core\TypeHint;

use PST\Core\Exceptions\KeyNotFoundException;

use InvalidArgumentException;

trait DictionaryTrait {
    use ReadOnlyDictionaryTrait {
        __construct as private __readOnlyDictionaryConstruct;
    }

    private bool $_allowOffsetInserts = false;

    public bool $allowOffsetInserts {
        get => $this->_allowOffsetInserts; 
        set(bool $value) { $this->_allowOffsetInserts = $value; }
    }

    public function __construct(null|iterable $items = null, null|TypeHint $TValue = null, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        $this->__readOnlyDictionaryConstruct($items, $TValue, $TKey, $disableTypeCheck);
    }

    // TODO: some kind of mode selection, where it can be toggled to allow for the createion of new element using offsetSet as apposed to just updating them and requiring new items to be inserted with the add method
    public function offsetSet(mixed $offset, mixed $value): void {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($offset))) {
            throw new InvalidArgumentException(
                "Type hint '{$this->TKey}' is not compatible with key of type '" . get_debug_type($offset) . "'."
            );
        }

        if (!$this->allowOffsetInserts && !array_key_exists($offset, $this->_items)) {
            throw new KeyNotFoundException("Key '{$offset}' not found in dictionary.", $offset);
        }

        if (!$this->TValue->isAssignableFrom(Type::ofInstance($value))) {
            throw new InvalidArgumentException(
                "Type hint '{$this->TValue}' is not compatible with value of type '" . get_debug_type($value) . "'."
            );
        }

        $this->_items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($offset))) {
            throw new InvalidArgumentException(
                "Type hint '{$this->TKey}' is not compatible with key of type '" . get_debug_type($offset) . "'."
            );
        }

        if (!array_key_exists($offset, $this->_items)) {
            throw new KeyNotFoundException("Key '{$offset}' not found in dictionary.", $offset);
        }

        unset($this->_items[$offset]);
    }

    public function add(int|string $key, mixed $value): void {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($key))) {
            throw new InvalidArgumentException(
                "Type hint '{$this->TKey}' is not compatible with key of type '" . get_debug_type($key) . "'."
            );
        }

        if (array_key_exists($key, $this->_items)) {
            throw new InvalidArgumentException("An element with the key '{$key}' already exists in the dictionary.");
        }

        if (!$this->TValue->isAssignableFrom(Type::ofInstance($value))) {
            throw new InvalidArgumentException(
                "Type hint '{$this->TValue}' is not compatible with value of type '" . get_debug_type($value) . "'."
            );
        }

        $this->_items[$key] = $value;
    }

    public function clear(): void {
        $this->_items = [];
    }

    public function tryAdd(int|string $key, mixed $value): bool {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($key))) {
            throw new InvalidArgumentException(
                "Key type '" . Type::ofInstance($key) . "' is not assignable to dictionary key type '{$this->TKey}'."
            );
        }

        if (array_key_exists($key, $this->_items)) {
            return false;
        }

        if (!$this->TValue->isAssignableFrom(Type::ofInstance($value))) {
            throw new InvalidArgumentException(
                "Value type '" . Type::ofInstance($value) . "' is not assignable to dictionary value type '{$this->TValue}'."
            );
        }

        $this->_items[$key] = $value;
        return true;
    }

    public function remove(int|string $key, mixed &$value = null): bool {
        if (!$this->TKey->isAssignableFrom(Type::ofInstance($key))) {
            throw new InvalidArgumentException(
                "Key type '" . Type::ofInstance($key) . "' is not assignable to dictionary key type '{$this->TKey}'."
            );
        }

        if (!array_key_exists($key, $this->_items)) {
            return false;
        }

        $value = $this->_items[$key];
        unset($this->_items[$key]);
        return true;
    }
}
