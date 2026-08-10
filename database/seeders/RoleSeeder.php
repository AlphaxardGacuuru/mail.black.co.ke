<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = collect([
            "Super Admin",
            "Admin",
            "Property Manager",
            "Unit Manager",
            "Tenant Manager",
            "Invoice Manager",
            "Payments Manager",
            "Credit Notes Manager",
            "Deductions Manager",
            "Emails Manager",
            "SMSs Manager",
            "Owner",
        ]);

        $roles->each(function($role) {
            Role::updateOrCreate(
                ["name" => $role],
                ["guard_name" => "web"]
            );
        });
    }
}
