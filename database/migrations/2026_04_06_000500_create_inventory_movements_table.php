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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->onDelete('restrict');
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->onDelete('restrict');
            $table->foreignId('production_order_id')->nullable();
            $table->enum('type', ['entrada', 'salida']);
            $table->decimal('quantity', 12, 4);
            $table->decimal('cost_price', 12, 4);
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['raw_material_id', 'movement_date']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
