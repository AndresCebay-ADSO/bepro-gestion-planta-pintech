<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_packaging_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('planned_units', 12, 4); // Cuántas unidades de este SKU se espera envasar
            $table->decimal('actual_units', 12, 4)->nullable(); // Cuántas se envasaron realmente
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('production_order_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_packaging_plan');
    }
};
