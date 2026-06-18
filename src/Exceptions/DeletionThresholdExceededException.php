<?php

declare(strict_types=1);

namespace JustBetter\MagentoProducts\Exceptions;

use Exception;

class DeletionThresholdExceededException extends Exception
{
    public function __construct(
        public readonly int $pendingDeletionCount,
        public readonly int $totalCount,
        public readonly float $threshold,
    ) {
        parent::__construct(sprintf(
            'Refusing to mark %d of %d products as deleted: ratio %.4f exceeds configured threshold %.4f.',
            $pendingDeletionCount,
            $totalCount,
            $pendingDeletionCount / $totalCount,
            $threshold,
        ));
    }
}
