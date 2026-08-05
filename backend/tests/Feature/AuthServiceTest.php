<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_login_returns_user_and_token(): void
    {
        $user = $this->createOwner();

        $result = app(AuthService::class)->login([
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $this->assertSame($user->id, $result['user']->id);
        $this->assertSame('owner@example.com', $result['user']->email);
        $this->assertIsString($result['token']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->expectException(AuthenticationException::class);

        app(AuthService::class)->login([
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->createOwner();

        $this->expectException(AuthenticationException::class);

        app(AuthService::class)->login([
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createOwner();
        $token = $user->createToken('owner');
        $user->withAccessToken($token->accessToken);

        $accessToken = $user->currentAccessToken();

        app(AuthService::class)->logout($user);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
    }

    public function test_me_returns_the_user(): void
    {
        $user = $this->createOwner();

        $this->assertSame($user->id, app(AuthService::class)->me($user)->id);
    }
}
