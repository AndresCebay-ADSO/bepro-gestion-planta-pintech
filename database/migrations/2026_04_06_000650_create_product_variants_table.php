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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 80)->unique();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('presentation_value', 12, 4)->nullable();
            $table->string('presentation_label', 50)->nullable(); // 1 gal, 5 gal, Kit 7.3 gal
            $table->string('color', 100)->nullable();
            $table->string('finish', 50)->nullable(); // Mate, Brillante, Semi brillante
            $table->string('base_type', 50)->nullable(); // Agua, Solvente, etc.
            $table->enum('component_system', ['1K', '2K', 'KIT'])->default('1K');
            $table->decimal('current_cost', 12, 4)->nullable();
            $table->decimal('current_price', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
            $table->index('sku');
            $table->index('color');
            $table->index('finish');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
