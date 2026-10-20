<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de termoencogido (3.8): cada uno es una receta de empaque secundario que se gasta por aplicación.
     */
    public function up(): void
    {
        Schema::create('shrink_wrap_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Único sin distinguir mayúsculas: el nombre se guarda como se escribe, y «planta cali» no puede convivir con
        // «Planta Cali» (auditoría 3.6, PR 8). PostgreSQL y SQLite aceptan índices sobre una expresión.
        DB::statement('CREATE UNIQUE INDEX shrink_wrap_types_name_lower_unique ON shrink_wrap_types (LOWER(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('shrink_wrap_types');
    }
};
