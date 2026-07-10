<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Association entre un groupe (section_user) et un cours.
 * Porte le volume horaire total et la durée par séance.
 *
 * Ex : "Le groupe section_user_id=5 suit le cours Maths,
 *       90h au total à raison de 3h par séance."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_user_id')
                ->constrained('section_users')
                ->cascadeOnDelete();
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('total_hours');
            $table->unsignedSmallInteger('hours_per_session');
            $table->string('reference', 50)->nullable();
            $table->text('description')->nullable();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections_courses');
    }
};
