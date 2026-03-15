<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use ArrayAccess;

interface IReadOnlyDictionary extends ArrayAccess, IEnumerator {
    /*********************************************************** PROPERTIES ************************************************************/
    public int $count {get;}
    public array $keys {get;}
    public array $values {get;}

    /************************************************************* METHODS *************************************************************/
    public function containsKey(int|string $key): bool;
    public function containsValue(mixed $value): bool;
    public function empty(): bool;
    public function tryGetValue($key, &$value): bool;
}
