<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('owner')->plainTextToken;
    }

    public function test_login_returns_user_and_token(): void
    {
        $this->createOwner();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.user.email', 'owner@example.com')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createOwner();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_validates_input(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createOwner();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'owner@example.com')
            ->assertJsonMissingPath('data.password');
    }

    public function test_logout_requires_token(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->createOwner();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_protected_endpoint_requires_token(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_protected_endpoint_accepts_valid_token(): void
    {
        $user = $this->createOwner();

        $this->withToken($this->tokenFor($user))->getJson('/api/orders')->assertOk();
    }

    public function test_guest_product_listing_stays_public(): void
    {
        $this->getJson('/api/products')->assertOk();
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => 'Chocolate Cake',
            'base_price' => 50000,
            'is_active' => true,
        ]);
    }

    public function test_guest_product_show_stays_public(): void
    {
        $product = $this->createProduct();

        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_guest_order_creation_stays_public(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Budi',
            'phone_number' => '081234567890',
            'pickup_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
    }
}
