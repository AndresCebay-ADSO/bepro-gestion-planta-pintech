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
        Schema::table('production_order_details', function (Blueprint $table) {
            $table->unsignedSmallInteger('step_order')->default(0)->after('raw_material_id');
            $table->index(['production_order_id', 'step_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_order_details', function (Blueprint $table) {
            $table->dropIndex(['production_order_id', 'step_order']);
            $table->dropColumn('step_order');
        });
    }
};
