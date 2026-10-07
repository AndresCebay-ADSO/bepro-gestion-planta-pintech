<?php

declare(strict_types=1);

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
        Schema::create('finished_product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Obligatoria (B48): el lote nace de una fila del plan de envasado, cuya presentación lo es, y la identidad del
            // lote es OP + presentación. Con NULL los índices únicos no chocarían (dos NULL nunca son iguales).
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            // Todo lote de PT nace al completar una OP (no hay entradas manuales que creen lotes) y se identifica por el
            // número de lote de esa OP más la presentación (B55).
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->decimal('initial_quantity', 12, 4);
            $table->date('entry_date');

            $table->index(['product_id', 'product_variant_id', 'entry_date']);
            $table->unique(['production_order_id', 'product_variant_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_product_batches');
    }
};
