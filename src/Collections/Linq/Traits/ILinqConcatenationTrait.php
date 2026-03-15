<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use PST\Core\Collections\IEnumerable;

interface ILinqConcatenationTrait {
    public function concat(iterable $second): IEnumerable;
    public function append(mixed $element): IEnumerable;
    public function prepend(mixed $element): IEnumerable;
    public function zip(iterable $second): IEnumerable;
}
