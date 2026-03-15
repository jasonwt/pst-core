<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

class ObjectDisposedException extends InvalidOperationException {
    public readonly null|string $objectName;

    public function __construct(null|string $objectName = null, string $message = "Cannot access a disposed object.", int $code = 0, null|\Throwable $previous = null) {
        $this->objectName = $objectName !== null && trim($objectName) !== ""
            ? trim($objectName)
            : null;

        $message = $this->objectName === null
            ? $message
            : "$message (Object name: '$this->objectName').";

        parent::__construct($message, $code, $previous);
    }
}
