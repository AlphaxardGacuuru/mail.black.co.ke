<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config("app.env") == "production") {
            $this->call([
                SubscriptionPlanSeeder::class,
                RoleSeeder::class,
                AdminUserSeeder::class,
            ]);
        } else {
            $this->call([
                SubscriptionPlanSeeder::class,
                RoleSeeder::class,
                AdminUserSeeder::class,
                UserSeeder::class,
                PropertySeeder::class,
            ]);
        }
    }
}
