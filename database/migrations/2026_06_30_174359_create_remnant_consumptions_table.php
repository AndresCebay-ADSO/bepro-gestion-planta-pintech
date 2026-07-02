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
        Schema::create('remnant_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remnant_id')->constrained('production_remnants')->restrictOnDelete();
            $table->foreignId('target_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->decimal('quantity_gallons', 12, 4);
            $table->decimal('quantity_kg', 12, 4);
            $table->foreignId('consumed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('consumed_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('remnant_id');
            $table->index('target_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remnant_consumptions');
    }
};
