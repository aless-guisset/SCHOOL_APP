<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute default_school_id sur la table users.
 * Séparé de extend_users_table car il dépend de la table schools (000003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_school_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('schools')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_school_id']);
            $table->dropColumn('default_school_id');
        });
    }
};
