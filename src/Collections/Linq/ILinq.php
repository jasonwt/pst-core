<?php

declare(strict_types=1);

namespace PST\Core\Collections\Linq;

use PST\Core\Collections\Linq\Traits\ILinqFilteringTrait;
use PST\Core\Collections\Linq\Traits\ILinqProjectionTrait;
use PST\Core\Collections\Linq\Traits\ILinqPartitioningTrait;
use PST\Core\Collections\Linq\Traits\ILinqElementAccessTrait;
use PST\Core\Collections\Linq\Traits\ILinqConcatenationTrait;
use PST\Core\Collections\Linq\Traits\ILinqConversionTrait;
use PST\Core\Collections\Linq\Traits\ILinqGroupingTrait;
use PST\Core\Collections\Linq\Traits\ILinqQuantifiersTrait;
use PST\Core\Collections\Linq\Traits\ILinqOrderingTrait;
use PST\Core\Collections\Linq\Traits\ILinqAggregationTrait;

interface ILinq extends
    ILinqFilteringTrait,
    ILinqProjectionTrait,
    ILinqPartitioningTrait,
    ILinqElementAccessTrait,
    ILinqConcatenationTrait,
    ILinqConversionTrait,
    ILinqGroupingTrait,
    ILinqQuantifiersTrait,
    ILinqOrderingTrait,
    ILinqAggregationTrait {
}
