<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use ArrayAccess;

interface IDictionary extends ArrayAccess, IEnumerator {
    /****************************************************** IMMUTABLE PROPERTIES *******************************************************/
    public int $count {get;}
    public array $keys {get;}
    public array $values {get;}

    /******************************************************* MUTABLE PROPERTIES ********************************************************/
    public bool $allowOffsetInserts {get; set;}

    /******************************************************* IMMUTABLE METHODS *********************************************************/
    public function containsKey(int|string $key): bool;
    public function containsValue(mixed $value): bool;
    public function empty(): bool;
    public function tryGetValue($key, &$value): bool;

    /******************************************************** MUTABLE METHODS **********************************************************/
    public function add(int|string $key, mixed $value): void;
    public function clear(): void;
    public function tryAdd(int|string $key, mixed $value): bool;
    public function remove(int|string $key, mixed &$value = null): bool;
}






    


    
