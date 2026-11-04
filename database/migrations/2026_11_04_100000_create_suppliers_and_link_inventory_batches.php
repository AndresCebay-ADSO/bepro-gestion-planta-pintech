<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de proveedores, que reemplaza el texto libre del lote. El texto viejo no se copia: no hay datos reales que
     * conservar. `supplier_id` admite nulos porque la carga inicial de lotes puede no traer proveedor.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('nit', 20)->nullable()->unique();
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Único sin distinguir mayúsculas, como los demás catálogos que conservan las mayúsculas del nombre.
        DB::statement('CREATE UNIQUE INDEX suppliers_name_lower_unique ON suppliers (LOWER(name))');

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropColumn('supplier');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('expiry_date')
                ->constrained('suppliers')
                ->restrictOnDelete();

            // Para ir de un proveedor a sus lotes: PostgreSQL no indexa las claves foráneas por sí solo.
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->string('supplier', 150)->nullable()->after('expiry_date');
        });

        Schema::dropIfExists('suppliers');
    }
};
