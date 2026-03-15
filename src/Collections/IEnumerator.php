<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use IteratorAggregate;

use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\ILinq;

interface IEnumerator extends IEnumerable, IteratorAggregate, ILinq {
    public mixed $current {get;}
}