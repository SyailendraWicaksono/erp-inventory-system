<?php

namespace Tests\Feature;

use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_resource_has_expected_shape(): void
    {
        $recipe = Recipe::factory()->create();
        RecipeDetail::factory()->create(['recipe_id' => $recipe->id]);

        $resource = json_decode((new RecipeResource($recipe->load('recipeDetails.rawMaterial')))->toJson(), true);

        $this->assertEquals($recipe->id, $resource['id']);
        $this->assertEquals($recipe->product_id, $resource['product_id']);
        $this->assertEquals($recipe->recipe_name, $resource['recipe_name']);
        $this->assertCount(1, $resource['recipe_details']);
        $this->assertArrayHasKey('raw_material_id', $resource['recipe_details'][0]);
        $this->assertArrayHasKey('raw_material_name', $resource['recipe_details'][0]);
        $this->assertArrayHasKey('quantity', $resource['recipe_details'][0]);
    }
}
