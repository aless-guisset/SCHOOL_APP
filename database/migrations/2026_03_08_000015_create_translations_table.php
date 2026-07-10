<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traductions multilingues de l'interface stockées en base de données.
 * screen_name : identifie le composant/écran concerné.
 * tag_id      : clé logique du label à traduire.
 * language_id : référence à la langue cible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('screen_name', 100)->nullable();
            $table->string('tag_key', 100)->comment('Clé logique du label (ex: dashboard.title)');
            $table->string('language_code', 10)->comment('Code ISO 639-1 (ex: fr, en, nl)');
            $table->text('translated_value');
            $table->string('reference', 50)->nullable();
            $table->text('description')->nullable();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Un tag ne peut avoir qu'une seule traduction par langue
            $table->unique(['tag_key', 'language_code']);
            $table->index(['language_code', 'screen_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
