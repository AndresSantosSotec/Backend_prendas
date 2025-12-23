<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador Sistema',
                'username' => 'admin',
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin'),
                'rol' => 'administrador',
                'activo' => true,
            ],
            [
                'name' => 'María García',
                'username' => 'cajero',
                'email' => 'cajero@sistema.com',
                'password' => Hash::make('cajero'),
                'rol' => 'cajero',
                'activo' => true,
            ],
            [
                'name' => 'Juan López',
                'username' => 'tasador',
                'email' => 'tasador@sistema.com',
                'password' => Hash::make('tasador'),
                'rol' => 'tasador',
                'activo' => true,
            ],
            [
                'name' => 'Ana Martínez',
                'username' => 'vendedor',
                'email' => 'vendedor@sistema.com',
                'password' => Hash::make('vendedor'),
                'rol' => 'vendedor',
                'activo' => true,
            ],
            [
                'name' => 'Carlos Rodríguez',
                'username' => 'supervisor',
                'email' => 'supervisor@sistema.com',
                'password' => Hash::make('supervisor'),
                'rol' => 'supervisor',
                'activo' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}

