<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cantine_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_user_id')->constrained('section_users')->cascadeOnDelete();
            // stripe_topup | manual_credit | order_debit | order_refund
            $table->string('type', 20);
            // signé : positif = crédit, négatif = débit
            $table->decimal('amount', 6, 2);
            $table->foreignId('cantine_order_id')->nullable()->constrained('cantine_orders')->nullOnDelete();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cantine_transactions');
    }
};
