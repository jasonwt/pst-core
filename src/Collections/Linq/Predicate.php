<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq;

use Closure;

use ReflectionFunction;

use PST\Core\Func;
use PST\Core\TypeHint;

use InvalidArgumentException;

class Predicate extends Func {
    public TypeHint $TItem {get => $this->TParameters[0];}
    public TypeHint $TKey {get => $this->TParameters[1];}

    public function __construct(Closure $predicate, TypeHint $TItem, null|TypeHint $TKey = null) {
        if ($TKey === null) {
            $TKey = TypeHint::key();
        } else if (!TypeHint::key()->isAssignableFrom($TKey)) {
            throw new InvalidArgumentException("Predicate key type must be assignable to int|string");
        }

        parent::__construct($predicate, TypeHint::bool(), $TItem, $TKey);
    }

    public static function of(Closure $func): static {
        $reflectionMethod = new ReflectionFunction($func);
        $TReturn = TypeHint::ofReflectionType($reflectionMethod->getReturnType());

        if (!$TReturn->isAssignableFrom(TypeHint::bool())) {
            throw new InvalidArgumentException("Predicate return type must be assignable from bool");
        }

        $TParameters = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $TParameters[] = TypeHint::ofReflectionType($param->getType());
        }

        return new static($func, ...$TParameters);
    }
}
