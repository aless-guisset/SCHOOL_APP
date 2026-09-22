<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoir (annonce simple : titre + description + échéance) rattaché à une
 * SectionCourse — voir docs/superpowers/specs/2026-09-22-mon-cours-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_id')
                ->constrained('sections_courses')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('due_date');
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
        Schema::dropIfExists('devoirs');
    }
};
