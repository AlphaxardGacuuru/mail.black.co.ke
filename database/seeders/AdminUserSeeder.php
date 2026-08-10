<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admins = [
            [
                'email' => 'al@property.black.co.ke',
                'name' => 'Super Admin',
                'avatar' => 'avatars/male-avatar.png',
                'phone' => null,
                'gender' => 'male',
                'password' => 'al@property.black.co.ke',
                'roles' => ['Super Admin'],
            ],
            [
                'email' => 'alphaxardgacuuru47@gmail.com',
                'name' => 'Alphaxard Gacuuru',
                'avatar' => 'avatars/male-avatar.png',
                'phone' => '0700364446',
                'gender' => 'male',
                'password' => 'alphaxardgacuuru47@gmail.com',
                'roles' => ['Super Admin'],
            ],
        ];

        collect($admins)->each(function (array $admin) {
            $user = User::firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'email_verified_at' => now(),
                    'avatar' => $admin['avatar'],
                    'phone' => $admin['phone'],
                    'password' => Hash::make($admin['password']),
                    'remember_token' => Str::random(10),
                    'gender' => $admin['gender'],
                ]
            );

            $user->syncRoles($admin['roles']);
        });
    }
}
