<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use Closure;
use PST\Core\Collections\IEnumerable;
use PST\Core\Collections\Linq\Predicate;

interface ILinqPartitioningTrait {
    public function skip(int $count): IEnumerable;
    public function skipWhile(Closure|Predicate $predicate): IEnumerable;
    public function skipUntil(Closure|Predicate $predicate): IEnumerable;
    public function skipLast(int $count): IEnumerable;
    public function take(int $count): IEnumerable;
    public function takeWhile(Closure|Predicate $predicate): IEnumerable;
    public function takeUntil(Closure|Predicate $predicate): IEnumerable;
    public function takeLast(int $count): IEnumerable;
    public function chunk(int $size): IEnumerable;
}
