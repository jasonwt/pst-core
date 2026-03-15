<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class NotImplementedException extends Exception {
    public function __construct(string $message = "The method or operation is not implemented.", int $code = 0, null|\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
