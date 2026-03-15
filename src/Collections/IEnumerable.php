<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use Traversable;

use PST\Core\TypeHint;
use PST\Core\Collections\Linq\ILinq;

interface IEnumerable extends Traversable, ILinq {
    public TypeHint $TKey {get;}
    public TypeHint $TValue {get;}

    public function getEnumerator(): IEnumerator;
}
