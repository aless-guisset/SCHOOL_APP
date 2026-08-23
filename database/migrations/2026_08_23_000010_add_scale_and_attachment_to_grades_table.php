<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal n'est pas une dépendance du projet — éviter
        // Blueprint::change() pour un simple élargissement de colonne.
        // MySQL uniquement : SQLite (connexion de test) n'a pas de syntaxe
        // MODIFY et n'impose de toute façon aucune contrainte réelle de
        // précision sur une colonne DECIMAL (typage dynamique).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE grades MODIFY grade DECIMAL(6,2) NOT NULL');
        }

        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('max_grade', 6, 2)->default(20.00)->after('grade');
            $table->string('attachment_path', 255)->nullable()->after('status');
            $table->string('attachment_original_name', 255)->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['max_grade', 'attachment_path', 'attachment_original_name']);
        });

        // Attention : ce rollback réduit `grade` à DECIMAL(4,2) (max 99.99) sans
        // avertissement — toute note déjà saisie au-delà sera tronquée ou fera
        // échouer le ALTER. Tradeoff assumé, non corrigé ici.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE grades MODIFY grade DECIMAL(4,2) NOT NULL');
        }
    }
};
