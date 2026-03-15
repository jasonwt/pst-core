<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq\Traits;

use PST\Core\Collections\Enumerator;
use PST\Core\Collections\IEnumerator;

trait LinqTraits {
    public abstract function getEnumerator(): IEnumerator;

    use LinqFilteringTrait;    
    use LinqProjectionTrait;
    use LinqOrderingTrait;
    use LinqPartitioningTrait;
    use LinqGroupingTrait;
    use LinqConversionTrait;
    use LinqGenerationTrait;
    use LinqConcatenationTrait;
    use LinqQuantifiersTrait;
    use LinqAggregationTrait;
    use LinqElementAccessTrait;
}
