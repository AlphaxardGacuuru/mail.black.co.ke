<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_roles_with_uuid_primary_keys(): void
    {
        $this->seed(RoleSeeder::class);

        $roleModel = (string) config('permission.models.role');
        $role = $roleModel::where('name', 'Super Admin')->firstOrFail();

        $this->assertNotNull($role->uuid);
        $this->assertSame($role->uuid, $role->getKey());
        $this->assertDatabaseHas('roles', [
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
    }
}
