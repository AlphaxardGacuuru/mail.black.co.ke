<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
        * Unit Based Subscriptions
        */

        SubscriptionPlan::updateOrCreate(
            ['name' => 'U 20'],
            [
                'name' => 'U 20',
                'description' => '0 - 20 units',
                'price' => [
                    "onboarding_fee" => 2000,
                    "monthly" => 2000,
                    "yearly" => 20000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 20,
                'max_units' => 20,
                'max_users' => 20,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['name' => 'U 50'],
            [
                'name' => 'U 50',
                'description' => '21 - 50 units',
                'price' => [
                    "onboarding_fee" => 5000,
                    "monthly" => 5000,
                    "yearly" => 50000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 50,
                'max_units' => 50,
                'max_users' => 50,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['name' => 'U 100'],
            [
                'name' => 'U 100',
                'description' => '0 - 100 units',
                'price' => [
                    "onboarding_fee" => 10000,
                    "monthly" => 10000,
                    "yearly" => 100000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 100,
                'max_units' => 100,
                'max_users' => 100,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['name' => 'U 200'],
            [
                'name' => 'U 200',
                'description' => '101 - 200 units',
                'price' => [
                    "onboarding_fee" => 30000,
                    "monthly" => 20000,
                    "yearly" => 200000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 200,
                'max_units' => 200,
                'max_users' => 200,
            ]
        );

        /*
        * Property Based Subscriptions
        */

        SubscriptionPlan::updateOrCreate(
            ['name' => 'P 100'],
            [
                'name' => 'P 100',
                'description' => '51 - 100 properties',
                'price' => [
                    "onboarding_fee" => 10000,
                    "monthly" => 10000,
                    "yearly" => 100000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 100,
                'max_units' => 100,
                'max_users' => 100,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['name' => 'P 200'],
            [
                'name' => 'P 200',
                'description' => '101 - 200 properties',
                'price' => [
                    "onboarding_fee" => 15000,
                    "monthly" => 15000,
                    "yearly" => 150000,
                ],
                'features' => [
                    'Property Management',
                    'Occupancy Management',
                    'Billing',
                    'Water Management',
                    'Tenant Management',
                    'Staff Management',
                ],
                'max_properties' => 200,
                'max_units' => 200,
                'max_users' => 200,
            ]
        );
    }
}
