<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->decimal('gas_level_pct', 5, 2);
            $table->decimal('weight_kg', 8, 3);
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('battery_voltage', 4, 2)->nullable();
            $table->integer('rssi')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_readings');
    }
};
