<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq;

use PST\Core\Func;
use PST\Core\Type;
use PST\Core\TypeHint;

use Closure;
use ReflectionFunction;

use InvalidArgumentException;

class Accumulator extends Func {
    public TypeHint $TAccumulated {get => $this->TParameters[0];}
    public TypeHint $TItem {get => $this->TParameters[1];}
    public null|TypeHint $TIndex {get => $this->TParameters[2] ?? null;}

    public function __construct(Closure $accumulator, null|TypeHint $TResult, TypeHint $TAccumulated, TypeHint $TItem, null|TypeHint $TIndex = null) {
        $intType = Type::typeOf('int');

        if ($TIndex !== null && !$TIndex->isAssignableFrom($intType)) {
            throw new InvalidArgumentException("Accumulator index type must be assignable from int");
        }

        $TResult ??= $TAccumulated;

        if (!$TAccumulated->isAssignableFrom($TResult)) {
            throw new InvalidArgumentException("Accumulator return type must be assignable to accumulator state type");
        }

        parent::__construct(
            $accumulator,
            $TResult,
            $TAccumulated,
            $TItem,
            ...($TIndex !== null ? [$TIndex] : [])
        );
    }

    public static function of(Closure $func): static {
        $reflectionMethod = new ReflectionFunction($func);
        $TReturn = TypeHint::ofReflectionType($reflectionMethod->getReturnType());

        $TParameters = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $TParameters[] = TypeHint::ofReflectionType($param->getType());
        }

        $parameterCount = count($TParameters);
        if ($parameterCount < 2 || $parameterCount > 3) {
            throw new InvalidArgumentException("Accumulator must define exactly 2 or 3 parameters");
        }

        if ($TReturn->fullName === 'undefined') {
            $TReturn = $TParameters[0];
        }

        return new static($func, $TReturn, ...$TParameters);
    }
}
