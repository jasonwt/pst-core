<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq;

use Closure;

use PST\Core\Type;
use PST\Core\Func;
use PST\Core\TypeHint;

class Comparer extends Func {
    public function __construct(Closure $comparer, null|TypeHint $TLeft = null, null|TypeHint $TRight = null) {
        parent::__construct($comparer, Type::typeOf('int'), $TLeft ?? TypeHint::undefined(), $TRight ?? TypeHint::undefined());
    }
}
