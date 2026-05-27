<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('deposit_config_id')->nullable()->constrained('deposit_configurations')->onDelete('set null');
            $table->decimal('amount_required', 10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->enum('payment_method', ['MPESA', 'CASH', 'AIRTEL_MONEY', 'FLUTTERWAVE']);
            $table->string('mpesa_receipt_no')->nullable();
            $table->string('mpesa_checkout_request_id')->nullable();
            $table->enum('status', ['PENDING', 'COMPLETED', 'REFUNDED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deposits');
    }
};
