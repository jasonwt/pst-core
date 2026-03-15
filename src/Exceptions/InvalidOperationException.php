<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class InvalidOperationException extends Exception {
    public function __construct(string $message = "Operation is not valid due to the current state of the object.", int $code = 0, null|\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
