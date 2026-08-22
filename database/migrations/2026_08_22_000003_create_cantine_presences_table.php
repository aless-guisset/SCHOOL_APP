<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('cantine_presences');
    }
};
