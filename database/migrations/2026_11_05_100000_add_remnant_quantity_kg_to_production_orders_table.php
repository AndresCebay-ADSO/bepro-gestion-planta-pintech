<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El sobrante se pesa: se guardan los kilos digitados tal cual, porque reconstruirlos desde los galones calculados
     * perdería decimales (2,5 kg con 4,7 kg/gal se reabrirían como 2,4999 kg).
     */
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('remnant_quantity_kg', 12, 4)->nullable()->after('remnant_quantity_gallons');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('remnant_quantity_kg');
        });
    }
};
