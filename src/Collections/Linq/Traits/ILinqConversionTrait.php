<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use PST\Core\Collections\IDictionary;
use PST\Core\Collections\IReadOnlyDictionary;

interface ILinqConversionTrait {
    public function toArray(): array;
    public function toList(): array;
    public function toDictionary(): IDictionary;
    public function toReadOnlyDictionary(): IReadOnlyDictionary;
}
