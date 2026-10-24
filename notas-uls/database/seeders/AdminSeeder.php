<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@uls.edu.sv',
            'password' => 'Admin123*',
            'role' => 'admin',
            'must_change_password' => true,
        ]);
    }
}
