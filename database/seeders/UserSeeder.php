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
        // Verificar si el usuario ya existe
        $existingUser = User::where('email', 'claudio@pez.com.ar')->first();
        
        if (!$existingUser) {
            User::create([
                'name' => 'Claudio',
                'email' => 'claudio@pez.com.ar',
                'password' => Hash::make('1122'),
                'email_verified_at' => now(), // Opcional: marcar email como verificado
            ]);

            $this->command->info('Usuario Claudio creado exitosamente');
        } else {
            $this->command->info('Usuario Claudio ya existe');
        }

        // Opcional: Crear un usuario administrador adicional
        $adminUser = User::where('email', 'admin@sistema.com')->first();
        
        if (!$adminUser) {
            User::create([
                'name' => 'Administrador',
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]);

            $this->command->info('Usuario Administrador creado exitosamente');
        } else {
            $this->command->info('Usuario Administrador ya existe');
        }
    }
}