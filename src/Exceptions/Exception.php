<?php

declare(strict_types=1);

namespace PST\Core\Exceptions;

use PST\Core\CoreObjectTrait;

class Exception extends \Exception {
    use CoreObjectTrait {
        __construct as private __coreObjectTraitConstruct;
        __toString as private __coreObjectTraitToString;
    }

    public function __construct(string $message = "", int $code = 0, null|\Throwable $previous = null) {
        $this->__coreObjectTraitConstruct();

        parent::__construct($message, $code, $previous);
    }

    public function __toString(): string {
        return parent::__toString();
    }
}
