<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entornos que ya ejecutaron la migración original de formula_details antes del
 * cambio de step_order / unique necesitan esta migración incremental.
 * Instalaciones nuevas con migrate:fresh ya tienen el esquema final en create_formula_details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formula_details')) {
            return;
        }

        Schema::table('formula_details', function (Blueprint $table) {
            // Quitar unique legacy si existe (nombre por convención Laravel).
            // Decisión de negocio: la misma MP puede repetirse en pasos distintos de la fórmula.
            $indexes = Schema::getIndexes('formula_details');
            foreach ($indexes as $index) {
                $cols = $index['columns'] ?? [];
                if ($cols === ['formula_id', 'raw_material_id'] && ($index['unique'] ?? false)) {
                    $table->dropUnique(['formula_id', 'raw_material_id']);

                    break;
                }
            }

            if (! Schema::hasColumn('formula_details', 'step_order')) {
                $table->unsignedSmallInteger('step_order')->default(0)->after('unit_of_measure_id');
            }

            // Índice compuesto (si ya existe por migrate:create, DB lo ignora en algunos drivers — aquí solo si falta)
            $hasFormulaStepIndex = collect($indexes)->contains(function (array $idx): bool {
                return ($idx['columns'] ?? []) === ['formula_id', 'step_order'];
            });
            if (! $hasFormulaStepIndex) {
                $table->index(['formula_id', 'step_order']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formula_details')) {
            return;
        }

        Schema::table('formula_details', function (Blueprint $table) {
            $indexes = Schema::getIndexes('formula_details');
            foreach ($indexes as $index) {
                $cols = $index['columns'] ?? [];
                if ($cols === ['formula_id', 'step_order']) {
                    $table->dropIndex(['formula_id', 'step_order']);

                    break;
                }
            }

            if (Schema::hasColumn('formula_details', 'step_order')) {
                $table->dropColumn('step_order');
            }

            $table->unique(['formula_id', 'raw_material_id']);
        });
    }
};
