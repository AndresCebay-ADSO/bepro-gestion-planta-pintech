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
        Schema::create('formula_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('formulas')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->foreignId('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->unsignedSmallInteger('step_order')->default(0);
            $table->timestamps();

            $table->index(['formula_id', 'step_order']);
            $table->index('raw_material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula_details');
    }
};
