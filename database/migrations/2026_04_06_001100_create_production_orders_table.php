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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->integer('lot_number')->nullable()->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('formula_id')->constrained('formulas')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->nullable();

            // Métricas de Rendimiento (Yield)
            $table->decimal('yield_real_quantity', 12, 4)->nullable();
            $table->decimal('yield_theoretical_quantity', 12, 4)->nullable();
            $table->decimal('yield_variance_quantity', 12, 4)->nullable();
            $table->decimal('yield_percentage', 5, 2)->nullable();

            $table->enum('status', ['pending', 'in_progress', 'pending_review', 'completed', 'cancelled'])->default('pending');
            $table->date('planned_date');
            $table->date('completion_date')->nullable();
            $table->text('notes')->nullable();

            // Agitación y Mezcla
            $table->dateTime('agitation_start_time')->nullable();
            $table->dateTime('agitation_end_time')->nullable();

            // Calidad
            $table->decimal('viscosity_ku', 8, 2)->nullable()->comment('Viscosidad en unidades Krebs (KU)');
            $table->decimal('grinding_hg', 8, 2)->nullable()->comment('Molienda en unidades Hegman (HG)');
            $table->decimal('quality_solids', 5, 2)->nullable()->comment('Porcentaje de sólidos');

            // Operación y Cierre
            $table->string('responsible_name', 150)->nullable();
            $table->dateTime('packaging_start_time')->nullable();
            $table->dateTime('packaging_end_time')->nullable();
            $table->decimal('spillage_quantity', 12, 4)->default(0)->comment('Cantidad de derrame detectado');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
