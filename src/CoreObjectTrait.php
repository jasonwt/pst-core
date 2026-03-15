<?php

declare(strict_types=1);

namespace PST\Core;

use InvalidArgumentException;
use WeakMap;

final class KeyedValueSequencer {
    private static array $_sequenceCache = [];
    private static null|WeakMap $_objectSequenceMap = null;

    private function __construct() {} // pure static class

    public static function aquireNext(string $key): int {
        if (($key = trim($key)) === '') {
            throw new InvalidArgumentException("Key cannot be empty or whitespace.");
        }

        static::$_sequenceCache[$key] ??= -1;

        return (static::$_sequenceCache[$key]++);
    }

    public static function aquireForObject(object $instance, string $key): int {
        static::$_objectSequenceMap ??= new WeakMap();

        if (!isset(static::$_objectSequenceMap[$instance])) {
            static::$_objectSequenceMap[$instance] = static::aquireNext($key);
        }

        return static::$_objectSequenceMap[$instance];
    }
}

trait CoreObjectTrait {
    protected function __construct() {}

    public function __toString(): string {
        return static::class;
    }

    public function getHashCode(): int {
        return KeyedValueSequencer::aquireForObject($this, static::class);
    }

    public function getType(): Type {
        return Type::typeOf(static::class);
    }
}
