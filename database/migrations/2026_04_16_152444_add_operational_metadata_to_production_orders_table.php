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
        Schema::table('production_orders', function (Blueprint $table) {
            // Agitación y Mezcla
            $table->dateTime('agitation_start_time')->nullable()->after('status');
            $table->dateTime('agitation_end_time')->nullable()->after('agitation_start_time');
            
            // Calidad
            $table->decimal('viscosity_ku', 8, 2)->nullable()->after('agitation_end_time')->comment('Viscosidad en unidades Krebs (KU)');
            $table->decimal('grinding_hg', 8, 2)->nullable()->after('viscosity_ku')->comment('Molienda en unidades Hegman (HG)');
            
            // Operación y Cierre
            $table->string('responsible_name')->nullable()->after('grinding_hg');
            $table->dateTime('packaging_start_time')->nullable()->after('responsible_name');
            $table->dateTime('packaging_end_time')->nullable()->after('packaging_start_time');
            $table->decimal('spillage_quantity', 12, 4)->default(0)->after('packaging_end_time')->comment('Cantidad de derrame detectado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn([
                'agitation_start_time',
                'agitation_end_time',
                'viscosity_ku',
                'grinding_hg',
                'responsible_name',
                'packaging_start_time',
                'packaging_end_time',
                'spillage_quantity',
            ]);
        });
    }
};
