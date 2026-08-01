<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RawMaterialService
{
    public function getAll(): Collection
    {
        return RawMaterial::latest()->get();
    }

    public function getById(int $id): RawMaterial
    {
        return RawMaterial::findOrFail($id);
    }

    public function create(array $data): RawMaterial
    {
        return DB::transaction(fn () => RawMaterial::create($data));
    }

    public function update(int $id, array $data): RawMaterial
    {
        return DB::transaction(function () use ($id, $data) {
            $rawMaterial = $this->getById($id);
            $rawMaterial->update($data);

            return $rawMaterial;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $this->getById($id)->delete();
        });
    }
}
