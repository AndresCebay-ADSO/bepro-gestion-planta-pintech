<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de proveedores de materia prima.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
