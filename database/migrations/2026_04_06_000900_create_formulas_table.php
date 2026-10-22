<?php

declare(strict_types=1);

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
        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('version')->default(1);
            // Nombre corto para distinguir las activas de un producto en el selector de la OP («RAL 7035»).
            $table->string('name', 60)->nullable();
            $table->boolean('is_active')->default(true);
            // Varias pueden estar activas (se produce con cualquiera); solo esta costea el producto (D-FOR1).
            $table->boolean('is_price_reference')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'is_active']);
            $table->index('version');
        });

        // Una sola fórmula de referencia por producto. PostgreSQL y SQLite aceptan índices parciales. Una migración
        // posterior que altere esta tabla debe comprobar que el índice sigue ahí: en SQLite, alterar una tabla la recrea.
        DB::statement('CREATE UNIQUE INDEX formulas_one_price_reference ON formulas (product_id) WHERE is_price_reference');

        // La de referencia está activa: no se costea con una fórmula con la que planta no puede producir. SQLite no
        // admite agregar un CHECK después de crear la tabla; la regla la aplican también las acciones.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE formulas ADD CONSTRAINT formulas_price_reference_is_active CHECK (NOT is_price_reference OR is_active)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formulas');
    }
};
