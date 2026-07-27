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
        Schema::table('finished_inventory_movements', function (Blueprint $table) {
            $table->foreignId('finished_product_batch_id')
                ->nullable()
                ->after('production_order_id')
                ->constrained('finished_product_batches')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finished_inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['finished_product_batch_id']);
            $table->dropColumn('finished_product_batch_id');
        });
    }
};
