<?php

namespace Tests\Feature;

use App\Http\Requests\RecipeRequest;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecipeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new RecipeRequest)->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 500.00],
            ],
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_recipe_name_is_required(): void
    {
        $validator = Validator::make([
            'recipe_details' => [
                ['raw_material_id' => 1, 'quantity' => 1],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_name', $validator->errors()->toArray());
    }

    public function test_recipe_details_requires_at_least_one_item(): void
    {
        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_details', $validator->errors()->toArray());
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 0],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recipe_details.0.quantity', $validator->errors()->toArray());
    }

    public function test_duplicate_raw_material_is_rejected(): void
    {
        $rawMaterial = RawMaterial::factory()->create();

        $validator = Validator::make([
            'recipe_name' => 'Chocolate Cake',
            'recipe_details' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 100],
                ['raw_material_id' => $rawMaterial->id, 'quantity' => 200],
            ],
        ], $this->rules());

        $this->assertTrue($validator->fails());
    }
}
