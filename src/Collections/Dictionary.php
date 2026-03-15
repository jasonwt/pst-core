<?php

declare(strict_types=1);

namespace PST\Core\Collections;

use PST\Core\TypeHint;
use PST\Core\CoreObject;

use PST\Core\Collections\Traits\DictionaryTrait;

final class Dictionary extends CoreObject implements IDictionary {
    use DictionaryTrait {
        __construct as private __dictionaryTraitConstruct;
    }

    public function __construct(null|iterable $items = null, null|TypeHint $TValue = null, null|TypeHint $TKey = null, null|bool $disableTypeCheck = null) {
        $this->__dictionaryTraitConstruct($items, $TValue, $TKey, $disableTypeCheck);
        parent::__construct();
    }
}
