<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cantine_presences');   // dépend de cantine_registrations, dans cet ordre
        Schema::dropIfExists('cantine_registrations');
    }

    public function down(): void
    {
        // Recrée les deux tables à l'identique de leurs migrations d'origine
        // (2026_08_22_000002/000003), pour qu'un rollback reste possible sans
        // perte de définition — même si les données, elles, sont perdues.
        Schema::create('cantine_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_user_id')->constrained('section_users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['section_user_id', 'day_of_week']);
        });

        Schema::create('cantine_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cantine_registration_id')->constrained('cantine_registrations')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_present')->default(true);
            $table->text('note')->nullable();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['cantine_registration_id', 'date']);
        });
    }
};
