<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend la table users avec les colonnes métier.
 * Note : default_school_id est ajouté dans 2026_03_08_000016
 * car il dépend de la table schools (créée en 000003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->string('reference', 50)->nullable()->after('phone_number');
            $table->text('description')->nullable()->after('reference');
            $table->char('status', 1)->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('status');
            $table->softDeletes()->after('remember_token');
            $table->unsignedBigInteger('created_by')->nullable()->after('updated_at');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'reference',
                'description',
                'status',
                'is_active',
                'deleted_at',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
