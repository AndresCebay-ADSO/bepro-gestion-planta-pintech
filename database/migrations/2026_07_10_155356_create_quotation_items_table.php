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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->index()->constrained('quotations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('type', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('color', 100)->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('list_unit_price', 16, 4);
            $table->decimal('price_adjustment_pct', 8, 4)->default(0);
            $table->decimal('unit_price', 16, 4);
            $table->decimal('subtotal', 16, 4);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
