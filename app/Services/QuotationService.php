<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly QuotationPricingService $pricingService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user): Quotation {
            $preparedItems = $this->prepareItems($data['items'] ?? []);
            $ivaPercentage = isset($data['iva_percentage']) ? (string) $data['iva_percentage'] : '19';
            $totals = $this->pricingService->calculateQuotationTotals($preparedItems, $ivaPercentage);

            $quotation = Quotation::create([
                'client_id' => $data['client_id'],
                'client_business_name' => $data['client_business_name'] ?? null,
                'client_nit' => $data['client_nit'] ?? null,
                'client_contact_name' => $data['client_contact_name'] ?? null,
                'client_phone' => $data['client_phone'] ?? null,
                'quotation_number' => $this->generateQuotationNumber(),
                'technology' => $data['technology'] ?? null,
                'line' => $data['line'] ?? null,
                'thickness_mils' => $data['thickness_mils'] ?? null,
                'application_method' => $data['application_method'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'validity_days' => $data['validity_days'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'area' => $data['area'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'iva_percentage' => $ivaPercentage,
                'iva_amount' => $totals['iva_amount'],
                'total' => $totals['total'],
                'status' => QuotationStatus::Draft,
                'created_by' => $user->id,
            ]);

            foreach ($preparedItems as $index => $item) {
                $quotation->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'type' => $item['type'] ?? null,
                    'description' => $item['description'],
                    'color' => $item['color'],
                    'quantity' => $item['quantity'],
                    'list_unit_price' => $item['list_unit_price'],
                    'price_adjustment_pct' => $item['price_adjustment_pct'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'sort_order' => $index + 1,
                ]);
            }

            return $quotation->load(['client', 'creator', 'items.product', 'items.productVariant']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data): Quotation {
            $lockedQuotation = Quotation::query()->where('id', $quotation->id)->lockForUpdate()->first();

            if ($lockedQuotation?->status !== QuotationStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => __('Solo se pueden editar cotizaciones en borrador.'),
                ]);
            }

            $preparedItems = $this->prepareItems($data['items'] ?? []);
            $ivaPercentage = isset($data['iva_percentage']) ? (string) $data['iva_percentage'] : '19';
            $totals = $this->pricingService->calculateQuotationTotals($preparedItems, $ivaPercentage);

            $quotation->update([
                'client_id' => $data['client_id'],
                'client_business_name' => $data['client_business_name'] ?? null,
                'client_nit' => $data['client_nit'] ?? null,
                'client_contact_name' => $data['client_contact_name'] ?? null,
                'client_phone' => $data['client_phone'] ?? null,
                'technology' => $data['technology'] ?? null,
                'line' => $data['line'] ?? null,
                'thickness_mils' => $data['thickness_mils'] ?? null,
                'application_method' => $data['application_method'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                'validity_days' => $data['validity_days'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'area' => $data['area'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'iva_percentage' => $ivaPercentage,
                'iva_amount' => $totals['iva_amount'],
                'total' => $totals['total'],
            ]);

            $quotation->items()->delete();

            $itemsToInsert = [];
            $now = now();

            foreach ($preparedItems as $index => $item) {
                $itemsToInsert[] = [
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'type' => $item['type'] ?? null,
                    'description' => $item['description'],
                    'color' => $item['color'],
                    'quantity' => $item['quantity'],
                    'list_unit_price' => $item['list_unit_price'],
                    'price_adjustment_pct' => $item['price_adjustment_pct'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $quotation->items()->insert($itemsToInsert);

            return $quotation->fresh(['client', 'creator', 'items.product', 'items.productVariant']);
        });
    }

    public function updateStatus(Quotation $quotation, QuotationStatus $status): Quotation
    {
        $quotation->update(['status' => $status]);

        return $quotation->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareItems(array $items): array
    {
        $variantIds = array_unique(array_map(
            fn (array $item): int => (int) $item['product_variant_id'],
            $items
        ));

        $variants = ProductVariant::query()
            ->with('product:id,description,name,sales_margin')
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $prepared = [];

        foreach ($items as $item) {
            $variant = $variants->get((int) $item['product_variant_id']);

            if ($variant === null || $variant->product_id !== (int) $item['product_id']) {
                throw ValidationException::withMessages([
                    'items' => __('Una o más presentaciones no son válidas para el producto seleccionado.'),
                ]);
            }

            $listUnitPrice = $this->pricingService->resolveListUnitPrice($variant);

            if ($listUnitPrice === null) {
                throw ValidationException::withMessages([
                    'items' => __('El producto :name no tiene precio de venta configurado.', [
                        'name' => $variant->product?->name ?? $variant->name,
                    ]),
                ]);
            }

            $pricing = $this->pricingService->resolveItemPricing(
                $listUnitPrice,
                isset($item['price_adjustment_pct']) ? (string) $item['price_adjustment_pct'] : null,
                isset($item['unit_price']) ? (string) $item['unit_price'] : null,
            );

            $quantity = (string) $item['quantity'];
            $subtotal = $this->pricingService->calculateLineSubtotal($quantity, $pricing['unit_price']);

            $prepared[] = [
                'product_id' => (int) $item['product_id'],
                'product_variant_id' => (int) $item['product_variant_id'],
                'type' => $item['type'] ?? null,
                'description' => $item['description'] ?? $variant->product?->description ?? $variant->product?->name,
                'color' => $item['color'] ?? null,
                'quantity' => $quantity,
                'list_unit_price' => $pricing['list_unit_price'],
                'price_adjustment_pct' => $pricing['price_adjustment_pct'],
                'unit_price' => $pricing['unit_price'],
                'subtotal' => $subtotal,
            ];
        }

        return $prepared;
    }

    private function generateQuotationNumber(): int
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(801339)');
        }

        $lastNumber = Quotation::query()->max('quotation_number');

        return $lastNumber !== null
            ? (int) $lastNumber + 1
            : (int) config('quotation.start_number', 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogProducts(): array
    {
        return Product::query()
            ->with([
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'sales_margin'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'description' => $product->description,
                'variants' => $product->variants->map(function (ProductVariant $variant) {
                    $listPrice = $this->pricingService->resolveListUnitPrice($variant);

                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'presentation_label' => $variant->presentation_label,
                        'presentation_value' => $variant->presentation_value,
                        'sales_price' => $listPrice !== null ? (float) $listPrice : null,
                    ];
                }),
            ])
            ->all();
    }
}
