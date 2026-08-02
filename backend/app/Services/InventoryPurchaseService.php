<?php

namespace App\Services;

use App\Models\InventoryPurchase;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPurchaseService
{
    public function getAll(): Collection
    {
        return InventoryPurchase::with('rawMaterial')->latest()->get();
    }

    public function getById(int $id): InventoryPurchase
    {
        return InventoryPurchase::with('rawMaterial')->findOrFail($id);
    }

    public function create(array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($data) {
            $data['purchase_date'] ??= now();
            $data['quantity'] = round((float) $data['quantity'], 2);

            $rawMaterial = $this->lockRawMaterial((int) $data['raw_material_id']);

            $purchase = InventoryPurchase::create($data);

            $this->setStock($rawMaterial, (float) $rawMaterial->stock_quantity + $data['quantity']);

            return $purchase->load('rawMaterial');
        });
    }

    public function update(int $id, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($id, $data) {
            $purchase = InventoryPurchase::whereKey($id)->lockForUpdate()->firstOrFail();

            $oldRawMaterial = $this->lockRawMaterial((int) $purchase->raw_material_id);

            $newRawMaterialId = (int) ($data['raw_material_id'] ?? $purchase->raw_material_id);
            $sameMaterial = $newRawMaterialId === $oldRawMaterial->id;
            $newRawMaterial = $sameMaterial
                ? $oldRawMaterial
                : $this->lockRawMaterial($newRawMaterialId);

            if (array_key_exists('quantity', $data)) {
                $data['quantity'] = round((float) $data['quantity'], 2);
            }

            $newQuantity = round((float) ($data['quantity'] ?? $purchase->quantity), 2);
            $oldQuantity = (float) $purchase->quantity;

            if ($sameMaterial) {
                $prospective = round((float) $oldRawMaterial->stock_quantity + ($newQuantity - $oldQuantity), 2);

                if ($prospective < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Stock cannot go below zero.'],
                    ]);
                }

                $purchase->update($data);
                $this->setStock($oldRawMaterial, $prospective);
            } else {
                $oldProspective = round((float) $oldRawMaterial->stock_quantity - $oldQuantity, 2);
                $newProspective = round((float) $newRawMaterial->stock_quantity + $newQuantity, 2);

                if ($oldProspective < 0 || $newProspective < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Stock cannot go below zero.'],
                    ]);
                }

                $purchase->update($data);
                $this->setStock($oldRawMaterial, $oldProspective);
                $this->setStock($newRawMaterial, $newProspective);
            }

            return $purchase->refresh()->load('rawMaterial');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $purchase = InventoryPurchase::whereKey($id)->lockForUpdate()->firstOrFail();
            $rawMaterial = $this->lockRawMaterial((int) $purchase->raw_material_id);

            $prospective = round((float) $rawMaterial->stock_quantity - (float) $purchase->quantity, 2);

            if ($prospective < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot go below zero.'],
                ]);
            }

            $purchase->delete();
            $this->setStock($rawMaterial, $prospective);
        });
    }

    private function lockRawMaterial(int $id): RawMaterial
    {
        return RawMaterial::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function setStock(RawMaterial $rawMaterial, float $newStock): void
    {
        $rawMaterial->update(['stock_quantity' => round($newStock, 2)]);
    }
}
