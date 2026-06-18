<?php

declare(strict_types=1);

namespace JustBetter\MagentoProducts\Tests\Actions;

use Illuminate\Support\Facades\Event;
use JustBetter\MagentoProducts\Actions\CheckRemovedProducts;
use JustBetter\MagentoProducts\Events\ProductDeletedInMagentoEvent;
use JustBetter\MagentoProducts\Exceptions\DeletionThresholdExceededException;
use JustBetter\MagentoProducts\Models\MagentoProduct;
use JustBetter\MagentoProducts\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CheckRemovedProductsTest extends TestCase
{
    #[Test]
    public function it_updates_existence_boolean(): void
    {
        Event::fake();

        config()->set('magento-products.deletion_threshold', null);

        MagentoProduct::query()->create([
            'sku' => '::sku_1::',
            'exists_in_magento' => true,
            'retrieved' => false,
        ]);

        MagentoProduct::query()->create([
            'sku' => '::sku_2::',
            'exists_in_magento' => false,
            'retrieved' => false,
        ]);

        /** @var CheckRemovedProducts $action */
        $action = app(CheckRemovedProducts::class);
        $action->check();

        /** @var ?MagentoProduct $removedProduct */
        $removedProduct = MagentoProduct::query()->firstWhere('sku', '=', '::sku_1::');

        $this->assertInstanceOf(MagentoProduct::class, $removedProduct);
        $this->assertFalse($removedProduct->exists_in_magento);

        Event::assertDispatchedTimes(ProductDeletedInMagentoEvent::class, 1);
    }

    #[Test]
    public function it_aborts_when_deletion_ratio_exceeds_threshold(): void
    {
        Event::fake();

        config()->set('magento-products.deletion_threshold', 0.1);

        // 1 product out of 5 existing products = 20% > 10% threshold.
        MagentoProduct::query()->create([
            'sku' => '::sku_1::',
            'exists_in_magento' => true,
            'retrieved' => false,
        ]);

        foreach (range(2, 5) as $i) {
            MagentoProduct::query()->create([
                'sku' => '::sku_'.$i.'::',
                'exists_in_magento' => true,
                'retrieved' => true,
            ]);
        }

        $action = app(CheckRemovedProducts::class);

        try {
            $action->check();
            $this->fail('Expected DeletionThresholdExceededException to be thrown.');
        } catch (DeletionThresholdExceededException $exception) {
            $this->assertSame(1, $exception->pendingDeletionCount);
            $this->assertSame(5, $exception->totalCount);
            $this->assertSame(0.1, $exception->threshold);
        }

        /** @var ?MagentoProduct $candidate */
        $candidate = MagentoProduct::query()->firstWhere('sku', '=', '::sku_1::');
        $this->assertInstanceOf(MagentoProduct::class, $candidate);
        $this->assertTrue($candidate->exists_in_magento);

        Event::assertNotDispatched(ProductDeletedInMagentoEvent::class);
    }

    #[Test]
    public function it_proceeds_when_deletion_ratio_is_within_threshold(): void
    {
        Event::fake();

        config()->set('magento-products.deletion_threshold', 0.5);

        // 1 deleted out of 5 existing products = 20% <= 50% threshold.
        MagentoProduct::query()->create([
            'sku' => '::sku_1::',
            'exists_in_magento' => true,
            'retrieved' => false,
        ]);

        foreach (range(2, 5) as $i) {
            MagentoProduct::query()->create([
                'sku' => '::sku_'.$i.'::',
                'exists_in_magento' => true,
                'retrieved' => true,
            ]);
        }

        $action = app(CheckRemovedProducts::class);
        $action->check();

        /** @var ?MagentoProduct $removedProduct */
        $removedProduct = MagentoProduct::query()->firstWhere('sku', '=', '::sku_1::');
        $this->assertInstanceOf(MagentoProduct::class, $removedProduct);
        $this->assertFalse($removedProduct->exists_in_magento);

        Event::assertDispatchedTimes(ProductDeletedInMagentoEvent::class, 1);
    }

    #[Test]
    public function it_proceeds_when_deletion_ratio_equals_threshold(): void
    {
        Event::fake();

        config()->set('magento-products.deletion_threshold', 0.1);

        // 1 deleted out of 10 existing products = 10% == 10% threshold.
        MagentoProduct::query()->create([
            'sku' => '::sku_1::',
            'exists_in_magento' => true,
            'retrieved' => false,
        ]);

        foreach (range(2, 10) as $i) {
            MagentoProduct::query()->create([
                'sku' => '::sku_'.$i.'::',
                'exists_in_magento' => true,
                'retrieved' => true,
            ]);
        }

        $action = app(CheckRemovedProducts::class);
        $action->check();

        /** @var ?MagentoProduct $removedProduct */
        $removedProduct = MagentoProduct::query()->firstWhere('sku', '=', '::sku_1::');
        $this->assertInstanceOf(MagentoProduct::class, $removedProduct);
        $this->assertFalse($removedProduct->exists_in_magento);

        Event::assertDispatchedTimes(ProductDeletedInMagentoEvent::class, 1);
    }

    #[Test]
    public function it_skips_threshold_check_when_disabled(): void
    {
        Event::fake();

        config()->set('magento-products.deletion_threshold', null);

        foreach (range(1, 3) as $i) {
            MagentoProduct::query()->create([
                'sku' => '::sku_'.$i.'::',
                'exists_in_magento' => true,
                'retrieved' => false,
            ]);
        }

        /** @var CheckRemovedProducts $action */
        $action = app(CheckRemovedProducts::class);
        $action->check();

        $this->assertSame(0, MagentoProduct::query()->where('exists_in_magento', '=', true)->count());

        Event::assertDispatchedTimes(ProductDeletedInMagentoEvent::class, 3);
    }
}
