<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class ArgumentException extends Exception {
    public readonly null|string $paramName;

    public function __construct(string $message = "An invalid argument was provided.", null|string $paramName = null, int $code = 0, null|\Throwable $previous = null) {
        $this->paramName = $paramName !== null && trim($paramName) !== ""
            ? trim($paramName)
            : null;

        $message = $this->paramName === null
            ? $message
            : "$message (Parameter '$this->paramName').";

        parent::__construct($message, $code, $previous);
    }
}
