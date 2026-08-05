<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new LoginRequest)->rules();
    }

    private function validPayload(): array
    {
        return [
            'email' => 'owner@example.com',
            'password' => 'password',
        ];
    }

    public function test_valid_payload_passes(): void
    {
        $validator = Validator::make($this->validPayload(), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_email_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['email']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_must_be_valid(): void
    {
        $data = $this->validPayload();
        $data['email'] = 'not-an-email';

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_password_is_required(): void
    {
        $data = $this->validPayload();
        unset($data['password']);

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
