<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class KeyNotFoundException extends Exception {
    public readonly null|int|string $key;

    public function __construct(string $message = "The given key was not present in the dictionary.", null|int|string $key = null, int $code = 0, null|\Throwable $previous = null) {
        $this->key = $key;

        if ($this->key !== null) {
            $message .= " (Key '$this->key').";
        }

        parent::__construct($message, $code, $previous);
    }
}
