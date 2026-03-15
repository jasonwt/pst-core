<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use PST\Core\Collections\Dictionary;
use PST\Core\Collections\IDictionary;
use PST\Core\Collections\IEnumerator;
use PST\Core\Collections\ReadOnlyDictionary;
use PST\Core\Collections\IReadOnlyDictionary;

trait LinqConversionTrait {
    public abstract function getEnumerator(): IEnumerator;

    public function toArray(): array {
        return iterator_to_array($this->getEnumerator());
    }

    public function toList(): array {
        return iterator_to_array($this->getEnumerator(), false);
    }

    public function toDictionary(): IDictionary {
        $thisEnumerator = $this->getEnumerator();

        return new Dictionary($thisEnumerator, $thisEnumerator->TValue, $thisEnumerator->TKey);
    }

    public function toReadOnlyDictionary(): IReadOnlyDictionary {
        $thisEnumerator = $this->getEnumerator();

        return new ReadOnlyDictionary($thisEnumerator, $thisEnumerator->TValue, $thisEnumerator->TKey);
    }
}
