<?php

declare(strict_types=1);

namespace JustBetter\MagentoProducts\Actions;

use JustBetter\MagentoProducts\Contracts\ChecksRemovedProducts;
use JustBetter\MagentoProducts\Events\ProductDeletedInMagentoEvent;
use JustBetter\MagentoProducts\Exceptions\DeletionThresholdExceededException;
use JustBetter\MagentoProducts\Models\MagentoProduct;

class CheckRemovedProducts implements ChecksRemovedProducts
{
    public function check(): void
    {
        $query = MagentoProduct::query()
            ->where('exists_in_magento', '=', true)
            ->where('retrieved', '=', false);

        $skus = $query->select(['sku'])->get();

        /** @var ?float $threshold */
        $threshold = config('magento-products.deletion_threshold');

        if ($threshold !== null) {
            $totalCount = MagentoProduct::query()
                ->where('exists_in_magento', '=', true)
                ->count();

            $ratio = $totalCount > 0 ? $skus->count() / $totalCount : 0.0;

            if ($ratio > $threshold) {
                throw new DeletionThresholdExceededException($skus->count(), $totalCount, $threshold);
            }
        }

        $query->update([
            'exists_in_magento' => false,
        ]);

        $skus->each(fn (MagentoProduct $product) => ProductDeletedInMagentoEvent::dispatch($product->sku));
    }

    public static function bind(): void
    {
        app()->singleton(ChecksRemovedProducts::class, static::class);
    }
}
