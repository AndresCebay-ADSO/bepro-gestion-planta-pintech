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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Único sin distinguir mayúsculas: el nombre se guarda como se escribe, y «planta cali» no puede convivir con
        // «Planta Cali» (auditoría 3.6, PR 8). PostgreSQL y SQLite aceptan índices sobre una expresión.
        DB::statement('CREATE UNIQUE INDEX product_categories_name_lower_unique ON product_categories (LOWER(name))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
