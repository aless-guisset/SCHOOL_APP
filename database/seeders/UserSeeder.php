<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── admin@school.com / admin1234 ─────────────────────────────────
        User::factory()->create([
            'firstname'         => 'Alessandro',
            'lastname'          => 'Admin',
            'email'             => 'admin@school.com',
            'password'          => Hash::make('admin1234'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // ── prof@school.com / password ───────────────────────────────────
        User::factory()->create([
            'firstname'         => 'Jean',
            'lastname'          => 'Professeur',
            'email'             => 'prof@school.com',
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // ── eleve@school.com / password ──────────────────────────────────
        User::factory()->create([
            'firstname'         => 'Marie',
            'lastname'          => 'Eleve',
            'email'             => 'eleve@school.com',
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // ── Utilisateurs aléatoires ──────────────────────────────────────
        User::factory(17)->create();
    }
}
