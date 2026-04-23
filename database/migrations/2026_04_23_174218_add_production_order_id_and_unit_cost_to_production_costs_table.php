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
        Schema::table('production_costs', function (Blueprint $table) {
            $table->foreignId('production_order_id')
                ->nullable()
                ->after('formula_id')
                ->constrained('production_orders')
                ->nullOnDelete();
            $table->decimal('unit_cost', 12, 4)->nullable()->after('cost');
            $table->unique('production_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            $table->dropUnique(['production_order_id']);
            $table->dropConstrainedForeignId('production_order_id');
            $table->dropColumn('unit_cost');
        });
    }
};
