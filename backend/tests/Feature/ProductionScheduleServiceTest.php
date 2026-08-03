<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionSchedule;
use App\Models\RawMaterial;
use App\Services\ProductionScheduleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductionScheduleService;
    }

    private function createOrderWithRecipe(int $itemQuantity = 1, float $materialStock = 100, float $recipeQuantity = 1): array
    {
        $material = RawMaterial::factory()->create([
            'name' => 'Flour',
            'stock_quantity' => $materialStock,
            'unit' => 'gram',
        ]);

        $product = Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $recipe = $product->recipes()->create(['recipe_name' => 'Cake A']);
        $recipe->recipeDetails()->create([
            'raw_material_id' => $material->id,
            'quantity' => $recipeQuantity,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $itemQuantity,
            'customization_note' => null,
            'subtotal' => 0,
        ]);

        return compact('order', 'material');
    }

    public function test_get_all_returns_all_schedules(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        ['order' => $orderB] = $this->createOrderWithRecipe();
        $this->service->create(['order_id' => $orderA->id]);
        $this->service->create(['order_id' => $orderB->id]);

        $this->assertCount(2, $this->service->getAll());
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getById(999999);
    }

    public function test_create_creates_schedule_as_scheduled(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();

        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->assertDatabaseHas('production_schedules', [
            'id' => $schedule->id,
            'order_id' => $order->id,
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ]);
        $this->assertNull($schedule->start_time);
    }

    public function test_create_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id]);
    }

    public function test_create_rejects_second_active_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id]);
    }

    public function test_create_throws_when_order_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create(['order_id' => 999999]);
    }

    public function test_create_validates_start_before_pickup(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $afterPickup = Carbon::parse($order->pickup_datetime)->addHour()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->create(['order_id' => $order->id, 'start_time' => $afterPickup]);
    }

    public function test_update_changes_times(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $start = now()->format('Y-m-d H:i:s');
        $end = now()->addHours(2)->format('Y-m-d H:i:s');

        $updated = $this->service->update($schedule->id, [
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $this->assertEquals($start, $updated->start_time);
        $this->assertEquals($end, $updated->end_time);
    }

    public function test_update_rejects_order_id_change_after_start(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $other = $this->createOrderWithRecipe()['order'];

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['order_id' => $other->id]);
    }

    public function test_update_rejects_order_id_change_to_occupied_order(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        ['order' => $orderB] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $orderA->id]);
        $this->service->create(['order_id' => $orderB->id]);

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['order_id' => $orderB->id]);
    }

    public function test_update_rejects_start_at_or_after_pickup(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $afterPickup = Carbon::parse($order->pickup_datetime)->addHour()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['start_time' => $afterPickup]);
    }

    public function test_update_rejects_end_before_start(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $start = now()->addHours(2)->format('Y-m-d H:i:s');
        $end = now()->format('Y-m-d H:i:s');

        $this->expectException(ValidationException::class);

        $this->service->update($schedule->id, ['start_time' => $start, 'end_time' => $end]);
    }

    public function test_delete_removes_scheduled_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->service->delete($schedule->id);

        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule->id]);
    }

    public function test_delete_rejects_in_progress_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($schedule->id);
    }

    public function test_delete_rejects_finished_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);
        $this->service->finish($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->delete($schedule->id);
    }

    public function test_start_marks_schedule_in_progress(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);

        $started = $this->service->start($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_IN_PROGRESS, $started->production_status);
        $this->assertNotNull($started->start_time);
    }

    public function test_start_rejects_when_not_scheduled(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_zero_item_order(): void
    {
        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_product_without_recipe(): void
    {
        $product = Product::create([
            'name' => 'Plain Cake',
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'customization_note' => null,
            'subtotal' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_rejects_shortage_and_rolls_back(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 10, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);

        try {
            $this->service->start($schedule->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('production_schedules', [
                'id' => $schedule->id,
                'production_status' => ProductionSchedule::STATUS_SCHEDULED,
            ]);
            $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
        }
    }

    public function test_start_uses_latest_recipe(): void
    {
        $material = RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 30, 'unit' => 'gram']);
        $product = Product::create(['name' => 'Chocolate Cake', 'base_price' => 50000, 'is_active' => true]);

        $recipeA = $product->recipes()->create(['recipe_name' => 'Cake A']);
        $recipeA->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 1]);

        $recipeB = $product->recipes()->create(['recipe_name' => 'Cake B']);
        $recipeB->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 50]);

        $order = Order::create([
            'customer_id' => Customer::factory()->create()->id,
            'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
            'pickup_datetime' => now()->addDay(),
            'order_status' => Order::ORDER_STATUS_CONFIRMED,
            'total_price' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'customization_note' => null,
            'subtotal' => 0,
        ]);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $this->expectException(ValidationException::class);

        $this->service->start($schedule->id);
    }

    public function test_start_aggregates_item_quantities(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe(2, 60, 30);
        $schedule = $this->service->create(['order_id' => $order->id]);

        $started = $this->service->start($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_IN_PROGRESS, $started->production_status);
    }

    public function test_finish_consumes_stock_and_marks_finished(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $finished = $this->service->finish($schedule->id);

        $this->assertSame(ProductionSchedule::STATUS_FINISHED, $finished->production_status);
        $this->assertNotNull($finished->end_time);
        $this->assertSame(Order::ORDER_STATUS_FINISHED, $order->refresh()->order_status);
        $this->assertEquals(50, (float) $material->refresh()->stock_quantity);
    }

    public function test_finish_rejects_when_not_in_progress(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);
        $this->service->finish($schedule->id);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedule->id);
    }

    public function test_finish_rejects_when_order_not_confirmed(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedule->id);
    }

    public function test_finish_rejects_shortage_and_rolls_back(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $material->update(['stock_quantity' => 10]);

        try {
            $this->service->finish($schedule->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
            $this->assertDatabaseHas('production_schedules', [
                'id' => $schedule->id,
                'production_status' => ProductionSchedule::STATUS_IN_PROGRESS,
            ]);
            $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $order->refresh()->order_status);
        }
    }

    public function test_finish_rounds_stock_to_two_decimals(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(3, 10, 0.33);
        $schedule = $this->service->create(['order_id' => $order->id]);
        $this->service->start($schedule->id);

        $this->service->finish($schedule->id);

        $this->assertEquals(9.01, (float) $material->refresh()->stock_quantity);
    }

    public function test_finish_serializes_orders_on_same_material(): void
    {
        $material = RawMaterial::factory()->create(['name' => 'Flour', 'stock_quantity' => 100, 'unit' => 'gram']);

        $schedules = [];
        foreach (['Cake A', 'Cake B'] as $name) {
            $product = Product::create(['name' => $name, 'base_price' => 50000, 'is_active' => true]);
            $recipe = $product->recipes()->create(['recipe_name' => $name.' recipe']);
            $recipe->recipeDetails()->create(['raw_material_id' => $material->id, 'quantity' => 60]);

            $order = Order::create([
                'customer_id' => Customer::factory()->create()->id,
                'order_number' => 'ORD-'.fake()->unique()->numerify('#####'),
                'pickup_datetime' => now()->addDay(),
                'order_status' => Order::ORDER_STATUS_CONFIRMED,
                'total_price' => 0,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'customization_note' => null,
                'subtotal' => 0,
            ]);

            $schedules[] = $this->service->create(['order_id' => $order->id]);
        }

        $this->service->start($schedules[0]->id);
        $this->service->start($schedules[1]->id);

        $this->service->finish($schedules[0]->id);
        $this->assertEquals(40, (float) $material->refresh()->stock_quantity);

        $this->expectException(ValidationException::class);

        $this->service->finish($schedules[1]->id);
    }
}
