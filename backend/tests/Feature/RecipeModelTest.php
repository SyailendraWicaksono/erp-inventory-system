<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\RecipeDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_factory_creates_recipe_with_product(): void
    {
        $recipe = Recipe::factory()->create();

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertNotNull($recipe->product);
    }

    public function test_recipe_detail_factory_creates_detail_with_relations(): void
    {
        $detail = RecipeDetail::factory()->create();

        $this->assertDatabaseHas('recipe_details', ['id' => $detail->id]);
        $this->assertNotNull($detail->recipe);
        $this->assertNotNull($detail->rawMaterial);
    }

    public function test_recipe_has_many_recipe_details(): void
    {
        $recipe = Recipe::factory()->create();
        RecipeDetail::factory()->count(2)->create(['recipe_id' => $recipe->id]);

        $this->assertCount(2, $recipe->recipeDetails);
    }
}
