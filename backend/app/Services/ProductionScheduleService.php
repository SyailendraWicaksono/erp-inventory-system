<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductionSchedule;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionScheduleService
{
    public function getAll(): Collection
    {
        return ProductionSchedule::with('order')->latest('id')->get();
    }

    public function getById(int $id): ProductionSchedule
    {
        return ProductionSchedule::with('order')->findOrFail($id);
    }

    public function create(array $data): ProductionSchedule
    {
        return DB::transaction(function () use ($data) {
            $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);
            $this->assertNoActiveSchedule($order);

            if (isset($data['start_time'])) {
                $this->assertStartBeforePickup($data['start_time'], (int) $order->id);
            }
            if (isset($data['start_time'], $data['end_time'])) {
                $this->assertEndAfterStart($data['start_time'], $data['end_time']);
            }

            $schedule = $order->productionSchedule()->create([
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'production_status' => ProductionSchedule::STATUS_SCHEDULED,
            ]);

            return $schedule->load('order');
        });
    }

    public function update(int $id, array $data): ProductionSchedule
    {
        return DB::transaction(function () use ($id, $data) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            $newOrderId = (int) $schedule->order_id;
            $newStartTime = $schedule->start_time;
            $newEndTime = $schedule->end_time;

            if (array_key_exists('order_id', $data)) {
                if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                    throw ValidationException::withMessages([
                        'order_id' => ['A started production schedule cannot be moved to another order.'],
                    ]);
                }

                $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
                $this->assertOrderConfirmed($order);
                $this->assertNoActiveSchedule($order, $schedule->id);

                $newOrderId = (int) $data['order_id'];
            }

            if (array_key_exists('start_time', $data)) {
                $newStartTime = $data['start_time'];
            }
            if (array_key_exists('end_time', $data)) {
                $newEndTime = $data['end_time'];
            }

            if ($newStartTime !== null) {
                $this->assertStartBeforePickup($newStartTime, $newOrderId);
            }
            if ($newStartTime !== null && $newEndTime !== null) {
                $this->assertEndAfterStart($newStartTime, $newEndTime);
            }

            $schedule->update([
                'order_id' => $newOrderId,
                'start_time' => $newStartTime,
                'end_time' => $newEndTime,
            ]);

            return $schedule->refresh()->load('order');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only a scheduled production can be deleted.'],
                ]);
            }

            $schedule->delete();
        });
    }

    public function start(int $id): ProductionSchedule
    {
        return DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only a scheduled production can be started.'],
                ]);
            }

            $order = Order::whereKey($schedule->order_id)->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);

            $order->load('items.product.recipes.recipeDetails.rawMaterial');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['The order has no items to produce.'],
                ]);
            }

            [$required, $missingRecipes] = $this->buildRequirements($order);

            if ($missingRecipes !== []) {
                throw ValidationException::withMessages([
                    'order_id' => ['Products have no recipe: '.implode(', ', $missingRecipes)],
                ]);
            }

            $materials = $this->lockMaterials(array_keys($required));
            $this->assertAvailability($required, $materials);

            $schedule->update([
                'start_time' => $schedule->start_time ?? now(),
                'production_status' => ProductionSchedule::STATUS_IN_PROGRESS,
            ]);

            return $schedule->refresh()->load('order');
        });
    }

    public function finish(int $id): ProductionSchedule
    {
        return DB::transaction(function () use ($id) {
            $schedule = ProductionSchedule::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($schedule->production_status !== ProductionSchedule::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'production_status' => ['Only an in-progress production can be finished.'],
                ]);
            }

            $order = Order::whereKey($schedule->order_id)->lockForUpdate()->firstOrFail();
            $this->assertOrderConfirmed($order);

            $order->load('items.product.recipes.recipeDetails.rawMaterial');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['The order has no items to produce.'],
                ]);
            }

            [$required, $missingRecipes] = $this->buildRequirements($order);

            if ($missingRecipes !== []) {
                throw ValidationException::withMessages([
                    'order_id' => ['Products have no recipe: '.implode(', ', $missingRecipes)],
                ]);
            }

            $materials = $this->lockMaterials(array_keys($required));
            $this->assertAvailability($required, $materials);

            foreach ($materials as $material) {
                $needed = $required[(int) $material->id];
                $material->update([
                    'stock_quantity' => round((float) $material->stock_quantity - $needed, 2),
                ]);
            }

            $schedule->update([
                'end_time' => $schedule->end_time ?? now(),
                'production_status' => ProductionSchedule::STATUS_FINISHED,
            ]);
            $order->update(['order_status' => Order::ORDER_STATUS_FINISHED]);

            return $schedule->refresh()->load('order');
        });
    }

    private function buildRequirements(Order $order): array
    {
        $required = [];
        $missingRecipes = [];

        foreach ($order->items as $item) {
            $recipe = $item->product->recipes->sortByDesc('id')->first();

            if ($recipe === null) {
                $missingRecipes[] = $item->product->name;

                continue;
            }

            foreach ($recipe->recipeDetails as $detail) {
                $materialId = (int) $detail->raw_material_id;
                $required[$materialId] = ($required[$materialId] ?? 0)
                    + ((float) $item->quantity * (float) $detail->quantity);
            }
        }

        foreach ($required as $materialId => $total) {
            $required[$materialId] = round($total, 2);
        }

        return [$required, $missingRecipes];
    }

    private function lockMaterials(array $materialIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $materialIds)));
        sort($ids);

        return RawMaterial::whereKey($ids)->lockForUpdate()->get();
    }

    private function assertAvailability(array $required, Collection $materials): void
    {
        $byId = $materials->keyBy('id');
        $shortages = [];

        foreach ($required as $materialId => $needed) {
            $material = $byId->get((int) $materialId);
            $available = (float) ($material->stock_quantity ?? 0);

            if ($needed > $available) {
                $shortages[] = sprintf(
                    'Insufficient stock for %s (required %.2f, available %.2f, short by %.2f).',
                    $material->name,
                    $needed,
                    $available,
                    $needed - $available
                );
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages(['stock' => $shortages]);
        }
    }

    private function assertOrderConfirmed(Order $order): void
    {
        if ($order->order_status !== Order::ORDER_STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'order_id' => ['The order must be confirmed.'],
            ]);
        }
    }

    private function assertNoActiveSchedule(Order $order, ?int $excludeScheduleId = null): void
    {
        $exists = $order->productionSchedule()
            ->where('production_status', '!=', ProductionSchedule::STATUS_FINISHED)
            ->when($excludeScheduleId !== null, fn ($query) => $query->where('id', '!=', $excludeScheduleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'order_id' => ['The order already has an active production schedule.'],
            ]);
        }
    }

    private function assertStartBeforePickup(mixed $startTime, int $orderId): void
    {
        $pickup = Order::whereKey($orderId)->value('pickup_datetime');

        if ($pickup === null || strtotime($startTime) >= strtotime($pickup)) {
            throw ValidationException::withMessages([
                'start_time' => ['Production must start before the order pickup time.'],
            ]);
        }
    }

    private function assertEndAfterStart(mixed $startTime, mixed $endTime): void
    {
        if (strtotime($endTime) <= strtotime($startTime)) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }
    }
}
