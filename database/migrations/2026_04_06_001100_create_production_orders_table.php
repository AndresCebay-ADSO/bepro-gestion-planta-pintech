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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_number', 20)->unique();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('formula_id')->constrained('formulas')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->decimal('quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->nullable();
            $table->decimal('yield_percentage', 5, 2)->nullable();
            $table->enum('status', ['pendiente', 'en_proceso', 'finalizada', 'cancelada'])->default('pendiente');
            $table->date('planned_date');
            $table->date('completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
