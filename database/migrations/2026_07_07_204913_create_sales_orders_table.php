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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'ready', 'delivered',  'cancelled'])->default('pending');
            $table->date('required_date')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('client_business_name')->nullable();
            $table->string('client_nit')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
