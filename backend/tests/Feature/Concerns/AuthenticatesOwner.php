<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait AuthenticatesOwner
{
    protected function authenticateOwner(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
    }
}
