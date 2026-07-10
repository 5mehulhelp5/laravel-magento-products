<?php

declare(strict_types=1);

namespace JustBetter\MagentoProducts\Actions;

use Illuminate\Support\Collection;
use JustBetter\MagentoProducts\Contracts\ChecksRemovedProducts;
use JustBetter\MagentoProducts\Events\ProductDeletedInMagentoEvent;
use JustBetter\MagentoProducts\Exceptions\DeletionThresholdExceededException;
use JustBetter\MagentoProducts\Models\MagentoProduct;

class CheckRemovedProducts implements ChecksRemovedProducts
{
    public function check(): void
    {
        $retrievedSkus = MagentoProduct::query()
            ->where('retrieved', '=', true)
            ->select('sku');

        /** @var Collection<int, string> $removedSkus */
        $removedSkus = MagentoProduct::query()
            ->where('exists_in_magento', '=', true)
            ->whereNotIn('sku', $retrievedSkus)
            ->distinct()
            ->pluck('sku');

        /** @var ?float $threshold */
        $threshold = config('magento-products.deletion_threshold');

        if ($threshold !== null) {
            $totalCount = MagentoProduct::query()
                ->where('exists_in_magento', '=', true)
                ->distinct()
                ->count('sku');

            $ratio = $totalCount > 0 ? $removedSkus->count() / $totalCount : 0;

            if ($ratio > $threshold) {
                throw new DeletionThresholdExceededException($removedSkus->count(), $totalCount, $threshold);
            }
        }

        $removedSkus
            ->chunk(500)
            ->each(fn (Collection $chunk) => MagentoProduct::query()
                ->whereIn('sku', $chunk->all())
                ->update(['exists_in_magento' => false]));

        $removedSkus->each(fn (string $sku) => ProductDeletedInMagentoEvent::dispatch($sku));
    }

    public static function bind(): void
    {
        app()->singleton(ChecksRemovedProducts::class, static::class);
    }
}
