<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->dropColumn('rate');
            // amount: KES credits charged per usage cycle, e.g. 1
            $table->decimal('amount', 10, 2)->after('name')->default(1);
            // unit: kg of gas dispensed per usage cycle, e.g. 0.00002
            $table->decimal('unit', 12, 8)->after('amount')->default(0.00002);
        });
    }

    public function down(): void
    {
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->dropColumn(['amount', 'unit']);
            $table->decimal('rate', 12, 8)->after('name');
        });
    }
};
