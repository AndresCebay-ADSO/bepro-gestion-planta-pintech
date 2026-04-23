<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('yield_real_quantity', 12, 4)->nullable()->after('actual_quantity');
            $table->decimal('yield_theoretical_quantity', 12, 4)->nullable()->after('yield_real_quantity');
            $table->decimal('yield_variance_quantity', 12, 4)->nullable()->after('yield_theoretical_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn([
                'yield_real_quantity',
                'yield_theoretical_quantity',
                'yield_variance_quantity',
            ]);
        });
    }
};
