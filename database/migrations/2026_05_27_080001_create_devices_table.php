<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('imei', 20)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('gas_level_pct', 5, 2)->default(0.00);
            $table->decimal('cylinder_size_kg', 8, 2);
            $table->boolean('valve_open')->default(false);
            $table->timestamp('last_seen')->nullable();
            $table->string('mqtt_topic')->unique();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', ['ONLINE', 'OFFLINE', 'LOW_GAS', 'FAULT'])->default('OFFLINE');
            $table->string('firmware_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
