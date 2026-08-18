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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unique('quotation_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unique('convert_to_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['quotation_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['convert_to_order_id']);
        });
    }
};
