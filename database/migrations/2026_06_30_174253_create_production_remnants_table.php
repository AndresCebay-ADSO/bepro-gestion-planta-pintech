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
        Schema::create('production_remnants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_order_id')->unique()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('original_quantity_gallons', 12, 4);
            $table->decimal('original_quantity_kg', 12, 4);
            $table->decimal('available_quantity_gallons', 12, 4);
            $table->decimal('available_quantity_kg', 12, 4);
            $table->decimal('density_kg_per_gallon', 10, 4);
            $table->decimal('cost_per_gallon', 12, 4)->nullable();
            $table->enum('status', ['available', 'partially_consumed', 'consumed'])->default('available');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();           
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_remnants');
    }
};
