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
        Schema::table('finished_inventory_movements', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 4)->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finished_inventory_movements', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
