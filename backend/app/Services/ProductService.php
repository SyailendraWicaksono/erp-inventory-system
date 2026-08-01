<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function getAll(): Collection
    {
        return Product::latest()->get();
    }

    public function getById(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->getById($id);
        $product->update($data);

        return $product;
    }

    public function delete(int $id): Product
    {
        $product = $this->getById($id);
        $product->delete();

        return $product;
    }
}
