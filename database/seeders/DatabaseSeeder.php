<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Outlet;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@barberaja.test',
            'password' => 'password',
        ]);

        $manager = User::query()->create([
            'name' => 'Demo Manager',
            'email' => 'manager@barberaja.test',
            'password' => 'password',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Demo Barber',
            'subscription_status' => 'active',
        ]);

        $outlet = Outlet::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Outlet',
            'slug' => 'demo-outlet',
            'address' => 'Jalan Demo No. 1',
        ]);

        UserRole::query()->create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        UserRole::query()->create([
            'user_id' => $manager->id,
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'role' => 'manager',
        ]);
    }
}
