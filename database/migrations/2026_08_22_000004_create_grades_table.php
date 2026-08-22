<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_user_id')->constrained('section_users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('period', 50); // ex : "Trimestre 1"
            $table->decimal('grade', 4, 2); // note sur 20, ex : 15.50
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['section_user_id', 'subject_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
