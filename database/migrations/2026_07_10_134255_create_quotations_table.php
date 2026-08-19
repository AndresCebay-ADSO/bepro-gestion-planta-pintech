<?php

use App\Enums\QuotationStatus;
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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('client_business_name')->nullable();
            $table->string('client_nit')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->unsignedInteger('quotation_number')->unique();
            $table->string('technology')->nullable();
            $table->string('line')->nullable();
            $table->string('thickness_mils')->nullable();
            $table->string('application_method')->nullable();
            $table->date('quotation_date')->nullable();
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('area')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 16, 4);
            $table->decimal('iva_percentage', 5, 2);
            $table->decimal('iva_amount', 16, 4);
            $table->decimal('total', 16, 4);
            $table->string('status', 20)->default(QuotationStatus::Draft->value)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('convert_to_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->index(['status', 'created_by']);
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS quotations_client_business_name_trgm ON quotations USING gin (client_business_name gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS quotations_client_nit_trgm ON quotations USING gin (client_nit gin_trgm_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
