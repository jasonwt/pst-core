<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;
trait LinqGenerationTrait {
    public abstract function getEnumerator(): IEnumerator;
    // ─── Generation (static) ─────────────────────────────────────────────────

    // range(int $start, int $count): static
    // repeat(mixed $element, int $count): static
    // empty(): static
}
