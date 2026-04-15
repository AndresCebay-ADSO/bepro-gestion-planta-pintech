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
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('initial_quantity', 12, 4);
            $table->decimal('remaining_quantity', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->date('entry_date');
            $table->date('expiry_date')->nullable();
            $table->string('supplier', 150)->nullable();
            $table->string('lot_number', 50)->nullable();
            $table->timestamps();

            $table->index(['raw_material_id', 'entry_date']);
            $table->index('warehouse_id');
            $table->index('remaining_quantity');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
