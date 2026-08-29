<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrateur', 'reference' => 'ADMIN',  'description' => 'Accès total, gestion de toutes les écoles.',        'status' => 'A', 'is_active' => true],
            ['name' => 'Power User',     'reference' => 'POWER',  'description' => 'Secrétariat étendu : horaires, utilisateurs.',      'status' => 'A', 'is_active' => true],
            ['name' => 'Directeur',      'reference' => 'DIR',    'description' => 'Gestion complète d\'une école spécifique.',         'status' => 'A', 'is_active' => true],
            ['name' => 'Secrétariat',    'reference' => 'SEC',    'description' => 'Gestion administrative de l\'école.',               'status' => 'A', 'is_active' => true],
            ['name' => 'Professeur',     'reference' => 'PROF',   'description' => 'Consultation de ses cours et feuilles de temps.',   'status' => 'A', 'is_active' => true],
            ['name' => 'Élève',          'reference' => 'ELEVE',  'description' => 'Consultation de son horaire et ses ressources.',    'status' => 'A', 'is_active' => true],
            ['name' => 'Parent',         'reference' => 'PARENT', 'description' => 'Suivi en lecture seule d\'un enfant (notes, horaire, présences).', 'status' => 'A', 'is_active' => true],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['reference' => $role['reference']], $role);
        }
    }
}
