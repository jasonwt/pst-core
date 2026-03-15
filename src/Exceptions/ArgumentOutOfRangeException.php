<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class ArgumentOutOfRangeException extends ArgumentException {
    public readonly mixed $actualValue;

    public function __construct(null|string $paramName = null, mixed $actualValue = null, string $message = "Specified argument was out of the range of valid values.", int $code = 0, null|\Throwable $previous = null) {
        $this->actualValue = $actualValue;

        parent::__construct($message, $paramName, $code, $previous);
    }
}
