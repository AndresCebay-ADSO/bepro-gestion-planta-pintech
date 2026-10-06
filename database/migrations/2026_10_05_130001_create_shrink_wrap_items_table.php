<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copia de la receta con que se registró un termoencogido: editar el tipo después no cambia el historial.
     */
    public function up(): void
    {
        Schema::create('shrink_wrap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shrink_wrap_id')->constrained('shrink_wraps')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->decimal('quantity_per_application', 12, 4);
            $table->decimal('quantity', 12, 4);
            $table->decimal('total_cost', 14, 4);
            $table->timestamps();

            $table->unique(['shrink_wrap_id', 'raw_material_id']);
            $table->index('raw_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shrink_wrap_items');
    }
};
