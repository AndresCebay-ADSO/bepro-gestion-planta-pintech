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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('business_name'); // razón social
            $table->string('nit')->nullable(); // NIT, puede ser null si es persona natural
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('shipping_address')->nullable();
            $table->timestamps();
            $table->softDeletes(); // un cliente no se borra, se desactiva
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
