<?php

declare(strict_types=1);

namespace PST\Core;

use Stringable;

abstract class CoreObject implements Stringable {
    use CoreObjectTrait {
        __construct as __coreObjectTraitConstruct;
    }

    protected function __construct() {
        $this->__coreObjectTraitConstruct();
    }
}