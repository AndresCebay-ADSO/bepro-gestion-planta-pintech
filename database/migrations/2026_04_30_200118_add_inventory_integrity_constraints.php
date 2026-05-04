<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index(['raw_material_id', 'warehouse_id', 'entry_date'], 'inventory_batches_raw_wh_entry_idx');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE inventory_batches ADD CONSTRAINT inventory_batches_initial_quantity_non_negative CHECK (initial_quantity >= 0)');
            DB::statement('ALTER TABLE inventory_batches ADD CONSTRAINT inventory_batches_remaining_quantity_non_negative CHECK (remaining_quantity >= 0)');
            DB::statement('ALTER TABLE inventory_batches ADD CONSTRAINT inventory_batches_remaining_lte_initial CHECK (remaining_quantity <= initial_quantity)');
            DB::statement('ALTER TABLE inventory_batches ADD CONSTRAINT inventory_batches_unit_price_non_negative CHECK (unit_price >= 0)');

            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_cost_price_non_negative CHECK (cost_price >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_movements_cost_price_non_negative');
            DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_movements_quantity_positive');

            DB::statement('ALTER TABLE inventory_batches DROP CONSTRAINT IF EXISTS inventory_batches_unit_price_non_negative');
            DB::statement('ALTER TABLE inventory_batches DROP CONSTRAINT IF EXISTS inventory_batches_remaining_lte_initial');
            DB::statement('ALTER TABLE inventory_batches DROP CONSTRAINT IF EXISTS inventory_batches_remaining_quantity_non_negative');
            DB::statement('ALTER TABLE inventory_batches DROP CONSTRAINT IF EXISTS inventory_batches_initial_quantity_non_negative');
        }

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex('inventory_batches_raw_wh_entry_idx');
        });
    }
};
