<?php

declare(strict_types=1);

namespace App\Actions\Quotations;

use App\Models\Quotation;

class BuildQuotationPdfDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Quotation $quotation): array
    {
        $quotation->loadMissing(['client', 'creator', 'items.product.category', 'items.productVariant']);

        return [
            'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date?->format('d/m/Y'),
            'validity_days' => $quotation->validity_days?->label(),
            'client' => [
                'business_name' => $quotation->client_business_name,
                'nit' => $quotation->client_nit,
                'contact_name' => $quotation->client_contact_name,
                'phone' => $quotation->client_phone,
            ],
            'technology' => $quotation->technology,
            'line' => $quotation->line,
            'thickness_mils' => $quotation->thickness_mils,
            'application_method' => $quotation->application_method,
            'payment_method' => $quotation->payment_method?->label(),
            'delivery_time' => $quotation->delivery_time,
            'area' => $quotation->area,
            'notes' => $quotation->notes,
            'subtotal' => (float) $quotation->subtotal,
            'iva_percentage' => (float) $quotation->iva_percentage,
            'iva_amount' => (float) $quotation->iva_amount,
            'total' => (float) $quotation->total,
            'advisor' => [
                'name' => $quotation->creator?->name,
                'job_title' => $quotation->creator?->job_title,
                'email' => $quotation->creator?->email,
                'phone' => $quotation->creator?->phone,
            ],
            'items' => $quotation->items->map(fn ($item) => [
                'sort_order' => $item->sort_order,
                'item_type' => $item->type?->label() ?? $item->product?->category?->name,
                'product_reference' => $item->product?->name,
                'description' => $item->description,
                'color' => $item->color,
                'presentation_label' => $item->productVariant?->presentation_label,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
        ];
    }
}
