<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ressource pédagogique (fichier ou lien) rattachée à une SectionCourse —
 * distinct du module "Ressources" (app/Models/Resource.php), qui est un
 * inventaire d'école (livres, matériel), pas du contenu de cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_id')
                ->constrained('sections_courses')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('type', 20)->comment('file, link');
            $table->string('url')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
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
        Schema::dropIfExists('course_resources');
    }
};
