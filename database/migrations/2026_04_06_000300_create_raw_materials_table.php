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
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('current_price', 12, 4);
            $table->decimal('previous_price', 12, 4)->nullable();
            $table->decimal('minimum_stock', 12, 4)->default(0);
            $table->integer('alert_days_before_expiry')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};
