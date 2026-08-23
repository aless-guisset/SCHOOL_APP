<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cantine_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->date('date');
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->char('status', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['school_id', 'date', 'label']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cantine_menus');
    }
};
