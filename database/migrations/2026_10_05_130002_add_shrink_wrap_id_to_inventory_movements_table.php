<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las salidas de un termoencogido se enlazan a su registro, no a la OP: su costo es gasto general y un reporte de
     * consumo por OP no debe contarlas. Va en una migración aparte porque `inventory_movements` es anterior a
     * `shrink_wraps` y la clave foránea no se puede declarar allí.
     */
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('shrink_wrap_id')
                ->nullable()
                ->after('production_order_id')
                ->constrained('shrink_wraps')
                ->restrictOnDelete();

            $table->index('shrink_wrap_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['shrink_wrap_id']);
            $table->dropConstrainedForeignId('shrink_wrap_id');
        });
    }
};
