<?php

declare(strict_types=1);

use App\Enums\PaintDevelopmentRequestStatus;
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
        Schema::create('paint_development_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('request_number')->unique();
            $table->string('status', 20)->default(PaintDevelopmentRequestStatus::Draft->value);

            // Identificación del requerimiento
            $table->string('client_name');
            $table->string('project_name');
            $table->string('responsible');
            $table->string('city');
            $table->date('sample_due_date');
            $table->string('current_product')->nullable();

            // Wizard pasos 1–4 (JSONB)
            $table->jsonb('context_payload')->nullable();
            $table->jsonb('performance_payload')->nullable();
            $table->jsonb('application_payload')->nullable();
            $table->jsonb('specifications_payload')->nullable();

            // Versionado del schema del formulario
            $table->unsignedTinyInteger('schema_version')->default(1);

            // Revisión por admin / producción
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Auditoría
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Índices para filtros comunes del listado
            $table->index(['status', 'created_by']);
            $table->index(['client_name', 'status']);
            $table->index(['status', 'sample_due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paint_development_requests');
    }
};
