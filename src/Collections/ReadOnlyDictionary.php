<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use PST\Core\TypeHint;
use PST\Core\CoreObject;
use PST\Core\Collections\Traits\ReadOnlyDictionaryTrait;

use PST\Core\Exceptions\InvalidOperationException;

final class ReadOnlyDictionary extends CoreObject implements IReadOnlyDictionary {
    use ReadOnlyDictionaryTrait {
        __construct as private __readOnlyDictionaryTraitConstruct;
    }

    public function __construct(null|iterable $items = null, null|TypeHint $TValue = null, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        $this->__readOnlyDictionaryTraitConstruct($items, $TValue, $TKey, $disableTypeCheck);
        parent::__construct();
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        throw new InvalidOperationException("Cannot modify read-only dictionary.");
    }

    public function offsetUnset(mixed $offset): void {
        throw new InvalidOperationException("Cannot modify read-only dictionary.");
    }
}
