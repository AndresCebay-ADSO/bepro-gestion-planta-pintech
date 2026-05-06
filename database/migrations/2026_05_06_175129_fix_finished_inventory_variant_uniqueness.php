<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropLegacyUniqueIndexes();

        Schema::table('finished_inventories', function (Blueprint $table) {
            $indexes = $this->listIndexes();

            if (! in_array('finished_inv_prod_var_wh_unique', $indexes, true)) {
                $table->unique(
                    ['product_id', 'product_variant_id', 'warehouse_id'],
                    'finished_inv_prod_var_wh_unique'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finished_inventories', function (Blueprint $table) {
            $indexes = $this->listIndexes();

            if (in_array('finished_inv_prod_var_wh_unique', $indexes, true)) {
                $table->dropUnique('finished_inv_prod_var_wh_unique');
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function listIndexes(): array
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'pgsql' => collect(DB::select(
                "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'finished_inventories'"
            ))->pluck('indexname')->all(),
            'sqlite' => collect(DB::select("PRAGMA index_list('finished_inventories')"))->pluck('name')->all(),
            default => [],
        };
    }

    private function dropLegacyUniqueIndexes(): void
    {
        $driver = DB::getDriverName();
        $indexes = $this->listIndexes();

        if ($driver === 'pgsql') {
            if (in_array('finished_inventories_product_id_warehouse_id_unique', $indexes, true)) {
                DB::statement('DROP INDEX IF EXISTS finished_inventories_product_id_warehouse_id_unique');
            }

            if (in_array('finished_inventories_product_variant_id_warehouse_id_unique', $indexes, true)) {
                DB::statement('DROP INDEX IF EXISTS finished_inventories_product_variant_id_warehouse_id_unique');
            }

            return;
        }

        if ($driver === 'sqlite') {
            if (in_array('finished_inventories_product_id_warehouse_id_unique', $indexes, true)) {
                DB::statement('DROP INDEX IF EXISTS finished_inventories_product_id_warehouse_id_unique');
            }

            if (in_array('finished_inventories_product_variant_id_warehouse_id_unique', $indexes, true)) {
                DB::statement('DROP INDEX IF EXISTS finished_inventories_product_variant_id_warehouse_id_unique');
            }
        }
    }
};
