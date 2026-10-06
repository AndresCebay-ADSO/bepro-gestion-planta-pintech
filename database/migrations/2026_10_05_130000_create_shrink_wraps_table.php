<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros de termoencogido (3.8): lo que el operario termoencogió de una OP completada, con un tipo y un número
     * de aplicaciones. Inmutables; su costo es gasto general y no entra al lote.
     */
    public function up(): void
    {
        Schema::create('shrink_wraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('shrink_wrap_type_id')->constrained('shrink_wrap_types')->restrictOnDelete();
            // Copiada de la OP: la bodega de la que salieron las bandejas y bolsas.
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('applications');
            // Fecha de planta en que se termoencogió (puede ser semanas después de completar la OP).
            $table->date('wrapped_at');
            $table->decimal('total_cost', 14, 4);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('wrapped_at');
            $table->index('production_order_id');
            // Filtro por tipo del listado, conteo «en uso» del catálogo y el RESTRICT al eliminar un tipo.
            $table->index('shrink_wrap_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shrink_wraps');
    }
};
