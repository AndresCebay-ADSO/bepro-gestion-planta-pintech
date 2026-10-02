<?php

declare(strict_types=1);

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
            // Empaque (3.7). Vacío = tantos como unidades envasadas. Los envases reutilizados no se descuentan.
            $table->decimal('new_containers_used', 12, 4)->nullable();
            // Envase que se consumió, guardado al completar: el documento no cambia si después se cambia el de la
            // presentación. Mientras la OP está abierta vale el de la presentación.
            $table->foreignId('package_raw_material_id')->nullable()->constrained('raw_materials')->restrictOnDelete();
            // Etiqueta: se copia de la presentación al agregar el plan y se puede cambiar mientras la OP esté abierta.
            $table->foreignId('label_raw_material_id')->nullable()->constrained('raw_materials')->restrictOnDelete();
            $table->decimal('labels_used', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('production_order_id');
            $table->index('product_variant_id');
            $table->index('package_raw_material_id');
            $table->index('label_raw_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_packaging_plan');
    }
};
