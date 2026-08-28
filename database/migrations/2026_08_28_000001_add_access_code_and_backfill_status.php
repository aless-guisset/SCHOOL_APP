<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('access_code', 12)->unique()->nullable()->after('status');
        });

        // Toutes les lignes existantes n'ont jamais eu de `status` renseigné —
        // elles représentent des accès déjà accordés (par l'Administrateur,
        // via les seeders, etc.). Le nouveau système traite `status='A'`
        // comme "accès actif" partout ; sans ce backfill, tous les
        // utilisateurs existants seraient bloqués dès que le filtrage
        // status='A' est ajouté (Task 3).
        DB::table('users_schools_roles')->whereNull('status')->update(['status' => 'A']);
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('access_code');
        });

        // Pas de rollback du backfill status : redevenir `null` ne changerait
        // rien de fonctionnel (le down() de Task 3 retirera de toute façon
        // les filtres qui en dépendent), et on ne peut pas distinguer a
        // posteriori les lignes qui étaient `null` de celles qui étaient déjà 'A'.
    }
};
