<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot : lie un utilisateur à une école avec un rôle.
 * C'est l'épine dorsale du système multi-école.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_schools_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Un utilisateur ne peut avoir qu'un seul rôle par école
            $table->unique(['user_id', 'school_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_schools_roles');
    }
};
