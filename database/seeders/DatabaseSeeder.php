<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::factory()->admin()->create([
            'nom' => 'Admin',
            'prenom' => 'Principal',
            'username' => 'admin',
            'email' => 'admin@sgp.local',
        ]);

        User::factory()->medecin()->create([
            'nom' => 'Kamga',
            'prenom' => 'Sophie',
            'username' => 'sophie.medecin',
            'email' => 'medecin@sgp.local',
        ]);

        User::factory()->create([
            'nom' => 'Talla',
            'prenom' => 'Eric',
            'username' => 'eric.agent',
            'email' => 'agent@sgp.local',
        ]);
    }
}
