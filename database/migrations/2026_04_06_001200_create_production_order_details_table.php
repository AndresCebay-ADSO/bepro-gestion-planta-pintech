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
        Schema::create('production_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->foreignId('batch_id')->constrained('inventory_batches')->onDelete('restrict');
            $table->foreignId('raw_material_id')->constrained('raw_materials')->onDelete('restrict');
            $table->decimal('planned_quantity', 12, 4); //lo que se planea consumir
            $table->decimal('actual_quantity', 12, 4)->nullable(); //lo que se consume realmente
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 4);
            $table->timestamps();

            $table->index('production_order_id');
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_details');
    }
};
