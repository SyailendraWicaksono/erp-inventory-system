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

            $rawMaterial = $this->lockRawMaterial((int) $data['raw_material_id']);

            $purchase = InventoryPurchase::create($data);

            $this->setStock($rawMaterial, (float) $rawMaterial->stock_quantity + (float) $data['quantity']);

            return $purchase->load('rawMaterial');
        });
    }

    public function update(int $id, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($id, $data) {
            $purchase = $this->getById($id);

            $oldRawMaterial = $this->lockRawMaterial((int) $purchase->raw_material_id);

            $newRawMaterialId = (int) ($data['raw_material_id'] ?? $purchase->raw_material_id);
            $sameMaterial = $newRawMaterialId === $oldRawMaterial->id;
            $newRawMaterial = $sameMaterial
                ? $oldRawMaterial
                : $this->lockRawMaterial($newRawMaterialId);

            $newQuantity = (float) ($data['quantity'] ?? $purchase->quantity);
            $oldQuantity = (float) $purchase->quantity;

            if ($sameMaterial) {
                $oldProspective = round((float) $oldRawMaterial->stock_quantity + ($newQuantity - $oldQuantity), 2);
                $newProspective = $oldProspective;
            } else {
                $oldProspective = round((float) $oldRawMaterial->stock_quantity - $oldQuantity, 2);
                $newProspective = round((float) $newRawMaterial->stock_quantity + $newQuantity, 2);
            }

            if ($oldProspective < 0 || $newProspective < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot go below zero.'],
                ]);
            }

            $purchase->update($data);
            $this->setStock($oldRawMaterial, $oldProspective);

            if (! $sameMaterial) {
                $this->setStock($newRawMaterial, $newProspective);
            }

            return $this->getById($purchase->id);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $purchase = $this->getById($id);
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
