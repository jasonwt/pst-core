<?php

declare(strict_types=1);

namespace PST\Core;

use Closure;

use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;

class Action extends CoreObject {
    private Closure $_closure;
    public readonly array $TParameters;

    public function __construct(Closure $closure, TypeHint ...$TParameters) {
        $this->_closure = $closure;
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

        return ($this->_closure)(...$args);
    }

    public function isAssignableFrom(TypeHint ...$TParameters): bool {
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

    public function isAssignableTo(TypeHint ...$TParameters): bool {
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

}
