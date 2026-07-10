<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Créneaux horaires récurrents liés à une association section-cours.
 * day_of_week : 1 = lundi … 7 = dimanche (ISO-8601).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_id')
                ->constrained('sections_courses')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('day_of_week')->comment('1=lundi … 7=dimanche');
            $table->time('start_time');
            $table->time('end_time');
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
        Schema::dropIfExists('schedules');
    }
};
