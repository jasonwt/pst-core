<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class ArgumentNullException extends ArgumentException {
    public function __construct(null|string $paramName = null, string $message = "Value cannot be null.", int $code = 0, null|\Throwable $previous = null) {
        parent::__construct($message, $paramName, $code, $previous);
    }
}
