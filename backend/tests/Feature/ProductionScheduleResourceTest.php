<?php

namespace Tests\Feature;

use App\Http\Resources\ProductionScheduleResource;
use App\Models\ProductionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionScheduleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_schedule_resource_has_expected_shape(): void
    {
        $schedule = ProductionSchedule::factory()->create([
            'start_time' => '2026-08-02 09:00:00',
            'end_time' => null,
            'production_status' => ProductionSchedule::STATUS_SCHEDULED,
        ]);
        $schedule->load('order');

        $resource = (new ProductionScheduleResource($schedule))->resolve();

        $this->assertEquals($schedule->id, $resource['id']);
        $this->assertEquals($schedule->order_id, $resource['order_id']);
        $this->assertEquals('2026-08-02 09:00:00', $resource['start_time']);
        $this->assertNull($resource['end_time']);
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $resource['production_status']);
        $this->assertEquals($schedule->order->order_number, $resource['order']['order_number']);
        $this->assertEquals($schedule->order->order_status, $resource['order']['order_status']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }
}
