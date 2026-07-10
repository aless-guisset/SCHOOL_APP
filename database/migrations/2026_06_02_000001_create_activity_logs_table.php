<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 20);                        // created | updated | deleted | login
            $table->string('model_type', 100)->nullable();      // ex: App\Models\School
            $table->unsignedBigInteger('model_id')->nullable(); // id de l'objet concerné
            $table->string('model_label', 150)->nullable();     // ex: "École Dupont"
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email', 150)->nullable();
            $table->json('changes')->nullable();                // before/after pour updated
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
