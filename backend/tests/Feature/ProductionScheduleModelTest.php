<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_production_schedule(): void
    {
        $schedule = ProductionSchedule::factory()->create();

        $this->assertDatabaseHas('production_schedules', ['id' => $schedule->id]);
        $this->assertSame(ProductionSchedule::STATUS_SCHEDULED, $schedule->production_status);
        $this->assertNull($schedule->start_time);
        $this->assertSame(Order::ORDER_STATUS_CONFIRMED, $schedule->order->order_status);
    }

    public function test_belongs_to_order(): void
    {
        $schedule = ProductionSchedule::factory()->create();

        $this->assertTrue($schedule->order->is(Order::find($schedule->order_id)));
    }
}
