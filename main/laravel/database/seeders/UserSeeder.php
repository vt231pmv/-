<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'      => 'Admin User',
                'email'     => 'admin@bank.com',
                'password'  => Hash::make('admin123'),
                'role'      => 'admin',
                'client_id' => null,
            ],
            [
                'name'      => 'Manager User',
                'email'     => 'manager@bank.com',
                'password'  => Hash::make('manager123'),
                'role'      => 'manager',
                'client_id' => null,
            ],
            [
                'name'      => 'Client User',
                'email'     => 'client@bank.com',
                'password'  => Hash::make('client123'),
                'role'      => 'client',
                'client_id' => 1,
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
