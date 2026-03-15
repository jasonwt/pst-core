<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class NotSupportedException extends Exception {
    public function __construct(string $message = "Specified method is not supported.", int $code = 0, null|\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
