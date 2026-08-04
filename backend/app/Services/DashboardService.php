<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionSchedule;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        private readonly InventoryAvailabilityService $inventoryAvailabilityService,
    ) {}

    public function getOverview(): array
    {
        return [
            'orders' => $this->getOrdersSummary(),
            'production' => $this->getProductionSummary(),
            'inventory' => $this->getInventorySummary(),
            'payments' => $this->getPaymentsSummary(),
        ];
    }

    public function getOrdersSummary(): array
    {
        $todayOrders = Order::query()->whereDate('created_at', today());

        return [
            'total_today' => (clone $todayOrders)->count(),
            'by_status' => $this->statusCounts($todayOrders, 'order_status', [
                Order::ORDER_STATUS_PENDING => 0,
                Order::ORDER_STATUS_CONFIRMED => 0,
                Order::ORDER_STATUS_FINISHED => 0,
                Order::ORDER_STATUS_COMPLETED => 0,
            ]),
            'active_orders' => (clone $todayOrders)
                ->with('customer')
                ->where('order_status', '!=', Order::ORDER_STATUS_COMPLETED)
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    public function getProductionSummary(): array
    {
        $todaySchedules = ProductionSchedule::query()->whereHas('order', function (Builder $query): void {
            $query->whereDate('pickup_datetime', today());
        });

        return [
            'total_today' => (clone $todaySchedules)->count(),
            'by_status' => $this->statusCounts($todaySchedules, 'production_status', [
                ProductionSchedule::STATUS_SCHEDULED => 0,
                ProductionSchedule::STATUS_IN_PROGRESS => 0,
                ProductionSchedule::STATUS_FINISHED => 0,
            ]),
            'active_schedules' => (clone $todaySchedules)
                ->with('order')
                ->whereIn('production_status', [
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_IN_PROGRESS,
                ])
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    public function getInventorySummary(): array
    {
        $statuses = $this->inventoryAvailabilityService->getStatus();

        return [
            'total' => $statuses->count(),
            'by_status' => [
                'available' => $statuses->where('status', 'available')->count(),
                'low' => $statuses->where('status', 'low')->count(),
                'out_of_stock' => $statuses->where('status', 'out_of_stock')->count(),
            ],
            'at_risk' => $statuses
                ->reject(fn ($material) => $material->status === 'available')
                ->take(10)
                ->values(),
        ];
    }

    public function getPaymentsSummary(): array
    {
        return [
            'paid_total_today' => (float) Payment::query()
                ->where('payment_status', Payment::PAYMENT_STATUS_PAID)
                ->whereDate('updated_at', today())
                ->sum('payment_amount'),
            'outstanding_total' => (float) Payment::query()
                ->where('payment_status', Payment::PAYMENT_STATUS_RECORDED)
                ->sum('payment_amount'),
            'by_status' => $this->statusCounts(Payment::query(), 'payment_status', [
                Payment::PAYMENT_STATUS_RECORDED => 0,
                Payment::PAYMENT_STATUS_PAID => 0,
            ]),
            'recorded_today' => Payment::query()
                ->with('order')
                ->where('payment_status', Payment::PAYMENT_STATUS_RECORDED)
                ->whereDate('payment_date', today())
                ->latest('id')
                ->take(10)
                ->get(),
        ];
    }

    private function statusCounts(Builder $query, string $column, array $defaults): array
    {
        $counts = (clone $query)
            ->selectRaw("$column, COUNT(*) as total")
            ->groupBy($column)
            ->get()
            ->pluck('total', $column)
            ->map(fn ($count) => (int) $count)
            ->all();

        return array_replace($defaults, $counts);
    }
}
