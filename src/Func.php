<?php

declare(strict_types=1);

namespace PST\Core;

use Closure;

use ReflectionFunction;

use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;
use PST\Core\Exceptions\InvalidOperationException;

class Func extends CoreObject {
    private Closure $_closure;
    
    public readonly TypeHint $TReturn;
    public readonly array $TParameters;

    public function __construct(Closure $func, null|TypeHint $TReturn, TypeHint ...$TParameters) {
        
        if ($TReturn === null) {
            $reflectionMethod = new ReflectionFunction($func);
            $TReturn = TypeHint::ofReflectionType($reflectionMethod->getReturnType());
        }

        $this->_closure = $func;
        $this->TReturn = $TReturn;
        $this->TParameters = $TParameters;

        parent::__construct();
    }
    
    public function __invoke(mixed ...$args): mixed {
        $expectedParameterCount = count($this->TParameters);
        $receivedParameterCount = count($args);

        if ($expectedParameterCount !== $receivedParameterCount) {
            throw new ArgumentOutOfRangeException(
                'args',
                $receivedParameterCount,
                "Parameter count mismatch. Expected $expectedParameterCount, got $receivedParameterCount."
            );
        }

        for ($i = 0; $i < $expectedParameterCount; $i++) {
            $expectedType = $this->TParameters[$i];
            $actualType = Type::ofInstance($args[$i]);

            if ($actualType !== null && $expectedType->isAssignableFrom($actualType)) {
                continue;
            }

            $actualTypeName = $actualType?->fullName ?? gettype($args[$i]);

            throw new ArgumentException(
                "Argument at index $i is not assignable to expected type '{$expectedType->fullName}'. Actual type: '$actualTypeName'.",
                "args[$i]"
            );
        }

        $result = ($this->_closure)(...$args);
        $actualResultType = Type::ofInstance($result);

        if ($actualResultType !== null && $this->TReturn->isAssignableFrom($actualResultType)) {
            return $result;
        }

        $actualResultTypeName = $actualResultType?->fullName ?? gettype($result);

        throw new InvalidOperationException(
            "Return value is not assignable to expected type '{$this->TReturn->fullName}'. Actual type: '$actualResultTypeName'."
        );
    }

    public function isAssignableFrom(TypeHint $TReturn, TypeHint ...$TParameters): bool {
        if (!$TReturn->isAssignableTo($this->TReturn)) {
            return false;
        }

        $paramCount = count($this->TParameters);
        if ($paramCount !== count($TParameters)) {
            return false;
        }

        for ($i = 0; $i < $paramCount; $i++) {
            if (!$this->TParameters[$i]->isAssignableTo($TParameters[$i])) {
                return false;
            }
        }

        return true;
    }

    public function isAssignableTo(TypeHint $TReturn, TypeHint ...$TParameters): bool {
        if (!$this->TReturn->isAssignableTo($TReturn)) {
            return false;
        }

        $paramCount = count($this->TParameters);
        if ($paramCount !== count($TParameters)) {
            return false;
        }

        for ($i = 0; $i < $paramCount; $i++) {
            if (!$this->TParameters[$i]->isAssignableFrom($TParameters[$i])) {
                return false;
            }
        }

        return true;
    }

    public static function of(Closure $func): static {
        $reflectionMethod = new ReflectionFunction($func);
        $TReturn = TypeHint::ofReflectionType($reflectionMethod->getReturnType());

        $TParameters = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $TParameters[] = TypeHint::ofReflectionType($param->getType());
        }

        return new static($func, $TReturn, ...$TParameters);
    }
}
