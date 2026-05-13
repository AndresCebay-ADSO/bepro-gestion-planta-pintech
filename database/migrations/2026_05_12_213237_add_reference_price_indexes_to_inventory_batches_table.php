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
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index(
                ['raw_material_id', 'remaining_quantity'],
                'inventory_batches_raw_remaining_idx'
            );

            $table->index(
                ['raw_material_id', 'entry_date', 'id'],
                'inventory_batches_raw_entry_id_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex('inventory_batches_raw_entry_id_idx');
            $table->dropIndex('inventory_batches_raw_remaining_idx');
        });
    }
};
