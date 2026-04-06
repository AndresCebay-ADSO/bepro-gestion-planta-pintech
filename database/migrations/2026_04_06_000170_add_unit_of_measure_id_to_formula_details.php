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
        Schema::table('formula_details', function (Blueprint $table) {
            $table->foreignId('unit_of_measure_id')->nullable()->after('quantity')->constrained('units_of_measure')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formula_details', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['unit_of_measure_id']);
            $table->dropColumn('unit_of_measure_id');
        });
    }
};
