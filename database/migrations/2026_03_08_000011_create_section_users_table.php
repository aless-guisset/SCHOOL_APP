<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription d'un utilisateur (via son rôle dans l'école) dans une section.
 * Représente typiquement : "cet élève appartient à la section 2ème A".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_school_role_id')
                ->constrained('users_schools_roles')
                ->cascadeOnDelete();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
            $table->string('reference', 50)->nullable();
            $table->text('description')->nullable();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['user_school_role_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_users');
    }
};
