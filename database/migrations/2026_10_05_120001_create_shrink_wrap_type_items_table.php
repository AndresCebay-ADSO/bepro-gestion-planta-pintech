<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receta de un tipo de termoencogido: cuánto de cada materia prima (bandejas, bolsas…) gasta una aplicación.
     */
    public function up(): void
    {
        Schema::create('shrink_wrap_type_items', function (Blueprint $table) {
            $table->id();
            // Las líneas no tienen historial propio: viven y mueren con su tipo.
            $table->foreignId('shrink_wrap_type_id')->constrained('shrink_wrap_types')->cascadeOnDelete();
            // RESTRICT: una materia prima en una receta no se puede eliminar (docs/POLITICA_ELIMINACION.md).
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();

            $table->unique(['shrink_wrap_type_id', 'raw_material_id']);
            $table->index('raw_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shrink_wrap_type_items');
    }
};
