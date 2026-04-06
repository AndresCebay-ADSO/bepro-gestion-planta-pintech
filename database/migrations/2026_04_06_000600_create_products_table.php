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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('restrict');
            $table->string('unit_of_measure', 20);
            $table->decimal('current_cost', 12, 4)->nullable();
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->decimal('current_price', 12, 4)->nullable();
            $table->decimal('price_threshold', 5, 2)->default(3.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
