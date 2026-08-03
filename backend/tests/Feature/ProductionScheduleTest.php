<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleTest extends TestCase
{
    use RefreshDatabase;

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
            'order_number' => 'ORD-' . fake()->unique()->numerify('#####'),
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

    public function test_index_returns_schedule_list(): void
    {
        ['order' => $orderA] = $this->createOrderWithRecipe();
        $scheduleA = $this->postJson('/api/production-schedules', ['order_id' => $orderA->id])->json('data');

        ['order' => $orderB] = $this->createOrderWithRecipe();
        $scheduleB = $this->postJson('/api/production-schedules', ['order_id' => $orderB->id])->json('data');

        $response = $this->getJson('/api/production-schedules');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $scheduleB['id'])
            ->assertJsonPath('data.0.order.order_number', $orderB->order_number);
    }

    public function test_store_creates_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.production_status', 'scheduled')
            ->assertJsonPath('data.order.order_status', 'confirmed');
        $this->assertDatabaseHas('production_schedules', ['order_id' => $order->id, 'production_status' => 'scheduled']);
    }

    public function test_store_rejects_second_active_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_rejects_non_confirmed_order(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

        $response = $this->postJson('/api/production-schedules', ['order_id' => $order->id]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->getJson("/api/production-schedules/{$schedule['id']}");

        $response->assertOk()->assertJsonPath('data.id', $schedule['id']);
    }

    public function test_update_updates_times(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $start = now()->format('Y-m-d H:i:s');
        $end = now()->addHours(2)->format('Y-m-d H:i:s');

        $response = $this->putJson("/api/production-schedules/{$schedule['id']}", [
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.start_time', $start)
            ->assertJsonPath('data.end_time', $end);
    }

    public function test_destroy_removes_scheduled_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->deleteJson("/api/production-schedules/{$schedule['id']}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule['id']]);
    }

    public function test_destroy_rejects_in_progress_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response = $this->deleteJson("/api/production-schedules/{$schedule['id']}");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseHas('production_schedules', ['id' => $schedule['id']]);
    }

    public function test_start_starts_production(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.production_status', 'in_progress');
    }

    public function test_start_rejects_shortage(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe(1, 10, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_finish_consumes_stock_and_marks_order_finished(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertOk()
            ->assertJsonPath('data.production_status', 'finished')
            ->assertJsonPath('data.order.order_status', 'finished');
        $this->assertEquals(50, (float) $material->refresh()->stock_quantity);
        $this->assertSame('finished', $order->refresh()->order_status);
    }

    public function test_finish_rejects_shortage(): void
    {
        ['order' => $order, 'material' => $material] = $this->createOrderWithRecipe(1, 100, 50);
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");

        $material->update(['stock_quantity' => 10]);

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertEquals(10, (float) $material->refresh()->stock_quantity);
        $this->assertSame('in_progress', $order->productionSchedule->refresh()->production_status);
    }

    public function test_finish_rejects_double_finish(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');
        $this->patchJson("/api/production-schedules/{$schedule['id']}/start");
        $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response = $this->patchJson("/api/production-schedules/{$schedule['id']}/finish");

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_schedule_returns_404(): void
    {
        $this->getJson('/api/production-schedules/999999')->assertNotFound();
    }

    public function test_store_validates_input(): void
    {
        $response = $this->postJson('/api/production-schedules', ['order_id' => 999999]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_cascade_order_deletion_removes_schedule(): void
    {
        ['order' => $order] = $this->createOrderWithRecipe();
        $schedule = $this->postJson('/api/production-schedules', ['order_id' => $order->id])->json('data');

        $order->delete();

        $this->assertDatabaseMissing('production_schedules', ['id' => $schedule['id']]);
    }
}
