<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_has_expected_shape(): void
    {
        $user = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);

        $resource = (new UserResource($user))->resolve();

        $this->assertEquals($user->id, $resource['id']);
        $this->assertEquals('Owner', $resource['name']);
        $this->assertEquals('owner@example.com', $resource['email']);
        $this->assertEquals($user->created_at, $resource['created_at']);
        $this->assertArrayNotHasKey('password', $resource);
        $this->assertArrayNotHasKey('remember_token', $resource);
    }
}
