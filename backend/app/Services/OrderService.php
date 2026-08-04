<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function getAll(): Collection
    {
        return Order::with(['customer', 'items.product', 'productionSchedule'])
            ->latest('id')
            ->get();
    }

    public function getById(int $id): Order
    {
        return Order::with(['customer', 'items.product', 'productionSchedule'])
            ->findOrFail($id);
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $this->assertPickupInFuture($data['pickup_datetime']);
            $this->assertItemsPresent($data['items']);
            $this->assertProductsExist(array_column($data['items'], 'product_id'));

            $order = Order::create([
                'customer_id' => $this->findOrCreateCustomer($data['phone_number'], $data['customer_name']),
                'order_number' => $this->buildOrderNumber(),
                'pickup_datetime' => $data['pickup_datetime'],
                'order_status' => Order::ORDER_STATUS_PENDING,
                'total_price' => 0,
            ]);

            $this->replaceItems($order, $data['items']);

            return $order->load(['customer', 'items.product', 'productionSchedule']);
        });
    }

    public function update(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertPending($order);

            if (array_key_exists('pickup_datetime', $data)) {
                $this->assertPickupInFuture($data['pickup_datetime']);
            }
            if (array_key_exists('items', $data)) {
                $this->assertItemsPresent($data['items']);
                $this->assertProductsExist(array_column($data['items'], 'product_id'));
            }

            $attributes = [];

            if (array_key_exists('pickup_datetime', $data)) {
                $attributes['pickup_datetime'] = $data['pickup_datetime'];
            }
            if (array_key_exists('phone_number', $data)) {
                $attributes['customer_id'] = $this->findOrCreateCustomer(
                    $data['phone_number'],
                    $data['customer_name'] ?? $order->customer?->name
                );
            }

            $order->update($attributes);

            if (array_key_exists('items', $data)) {
                $this->replaceItems($order, $data['items']);
            }

            return $order->refresh()->load(['customer', 'items.product', 'productionSchedule']);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertPending($order);

            $order->delete();
        });
    }

    public function confirm(int $id): Order
    {
        return DB::transaction(function () use ($id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($order->order_status !== Order::ORDER_STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'order_status' => ['Only a pending order can be confirmed.'],
                ]);
            }

            $this->assertPickupInFuture($order->pickup_datetime);

            $order->load('items');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['An order must have at least one item.'],
                ]);
            }

            $productIds = $order->items->pluck('product_id')->map('intval')->all();
            $productIds = array_values(array_unique($productIds));
            sort($productIds);

            $products = Product::whereKey($productIds)->lockForUpdate()->orderBy('id')->get()->keyBy('id');

            $total = 0;

            foreach ($order->items as $item) {
                $product = $products->get((int) $item->product_id);

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ['Every ordered product must be active.'],
                    ]);
                }

                if ((int) $item->quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Every item quantity must be greater than zero.'],
                    ]);
                }

                $unitPrice = round((float) $item->unit_price, 2);

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Every item unit price must be greater than zero.'],
                    ]);
                }

                $subtotal = round($unitPrice * (int) $item->quantity, 2);
                $total = round($total + $subtotal, 2);

                $item->update(['subtotal' => $subtotal]);
            }

            $order->update([
                'total_price' => $total,
                'order_status' => Order::ORDER_STATUS_CONFIRMED,
            ]);

            return $order->refresh()->load(['customer', 'items.product', 'productionSchedule']);
        });
    }

    private function replaceItems(Order $order, array $items): void
    {
        $productIds = array_column($items, 'product_id');
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        $prices = Product::whereKey($productIds)->pluck('base_price', 'id');

        $order->items()->delete();

        $total = 0;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $unitPrice = round(
                $this->resolveUnitPrice($item, (float) ($prices[$productId] ?? 0)),
                2
            );
            $subtotal = round($unitPrice * (int) $item['quantity'], 2);
            $total = round($total + $subtotal, 2);

            $order->items()->create([
                'product_id' => $productId,
                'quantity' => (int) $item['quantity'],
                'customization_note' => $item['customization_note'] ?? null,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        $order->update(['total_price' => $total]);
    }

    private function resolveUnitPrice(array $item, float $basePrice): float
    {
        if (array_key_exists('unit_price', $item) && $item['unit_price'] !== null) {
            return (float) $item['unit_price'];
        }

        return $basePrice;
    }

    private function findOrCreateCustomer(string $phoneNumber, ?string $name = null): int
    {
        $existing = Customer::where('phone_number', $phoneNumber)->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        try {
            return (int) Customer::create([
                'phone_number' => $phoneNumber,
                'name' => $name ?? $phoneNumber,
            ])->id;
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $id = Customer::where('phone_number', $phoneNumber)->value('id');

            if ($id === null) {
                throw $exception;
            }

            return (int) $id;
        }
    }

    private function buildOrderNumber(): string
    {
        $date = now()->format('Ymd');

        return sprintf('ORD-%s-%06d', $date, $this->nextSequenceValue($date));
    }

    private function nextSequenceValue(string $date): int
    {
        if (DB::getDriverName() === 'pgsql') {
            return (int) DB::selectOne(
                'INSERT INTO order_number_sequences (sequence_date, last_value)
                 VALUES (?, 1)
                 ON CONFLICT (sequence_date) DO UPDATE SET last_value = order_number_sequences.last_value + 1
                 RETURNING last_value',
                [$date]
            )->last_value;
        }

        $current = DB::table('order_number_sequences')
            ->where('sequence_date', $date)
            ->value('last_value');

        $next = (int) $current + 1;

        DB::table('order_number_sequences')
            ->updateOrInsert(
                ['sequence_date' => $date],
                ['last_value' => $next, 'updated_at' => now()]
            );

        return $next;
    }

    private function assertPending(Order $order): void
    {
        if ($order->order_status !== Order::ORDER_STATUS_PENDING) {
            throw ValidationException::withMessages([
                'order_status' => ['Only a pending order can be modified.'],
            ]);
        }
    }

    private function assertPickupInFuture(mixed $pickupDatetime): void
    {
        if (Carbon::parse($pickupDatetime)->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'pickup_datetime' => ['Pickup date must be in the future.'],
            ]);
        }
    }

    private function assertItemsPresent(array $items): void
    {
        if (count($items) < 1) {
            throw ValidationException::withMessages([
                'items' => ['An order must have at least one item.'],
            ]);
        }
    }

    private function assertProductsExist(array $productIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));

        if (Product::whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => ['One or more products do not exist.'],
            ]);
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if ($code === '23505') {
            return true;
        }

        return str_contains(strtolower((string) $exception->getMessage()), 'unique');
    }
}
