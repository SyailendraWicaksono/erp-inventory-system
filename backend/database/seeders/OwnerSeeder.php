<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('OWNER_EMAIL', 'owner@example.com')],
            [
                'name' => 'Owner',
                'password' => env('OWNER_PASSWORD', 'password'),
            ],
        );
    }
}
