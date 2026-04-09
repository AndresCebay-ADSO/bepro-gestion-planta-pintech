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
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // kg, lt, gal, ml, unidad
            $table->string('name', 100); // Kilogramo, Litro, Galón, Mililitro, Unidad
            $table->string('symbol', 10); // kg, L, gal, mL, u
            $table->text('description')->nullable();
            $table->decimal('to_kg_conversion', 10, 4)->nullable(); // Para conversiones de peso
            $table->decimal('to_liter_conversion', 10, 4)->nullable(); // Para conversiones de volumen
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
