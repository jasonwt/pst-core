<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use PST\Core\CoreObject;
use PST\Core\TypeHint;

use PST\Core\Collections\Traits\EnumeratorTrait;

final class Enumerator extends CoreObject implements IEnumerator {
    use EnumeratorTrait;

    public function __construct(null|iterable $items = null, null|TypeHint $TValue = null, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        $this->__constructEnumerableTrait($items, $TValue, $TKey, $disableTypeCheck);

        parent::__construct();
    }
}
