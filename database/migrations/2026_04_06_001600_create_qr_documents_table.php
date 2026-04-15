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
        Schema::create('qr_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->enum('document_type', ['ficha_tecnica', 'ficha_seguridad', 'certificado_calidad']);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->integer('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['qr_code_id', 'document_type']);
            $table->index('is_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_documents');
    }
};
