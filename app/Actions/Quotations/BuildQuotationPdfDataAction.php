<?php

declare(strict_types=1);

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class BuildQuotationPdfDataAction
{
    private const NOT_APPLICABLE = 'N.A.';

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
            'thickness_mils' => $this->formatThickness($quotation->thickness_mils),
            'application_method' => $quotation->application_method,
            'payment_method' => $quotation->payment_method?->label(),
            'delivery_time' => $quotation->delivery_time,
            'area' => $this->formatArea($quotation->area),
            'notes' => $quotation->notes,
            'subtotal' => (float) $quotation->subtotal,
            'iva_percentage' => (float) $quotation->iva_percentage,
            'iva_amount' => (float) $quotation->iva_amount,
            'total' => (float) $quotation->total,
            // El asesor de la cotización es quien la crea; firma con la suya.
            'advisor' => [
                'name' => $quotation->creator?->name,
                'job_title' => $quotation->creator?->job_title,
                'email' => $quotation->creator?->email,
                'phone' => $quotation->creator?->phone,
                'signature' => $this->signatureBase64($quotation->creator),
            ],
            'items' => $quotation->items->map(fn ($item) => [
                'sort_order' => $item->sort_order,
                'item_type' => $item->type?->label() ?? $item->product?->category?->name,
                'product_reference' => $item->product?->name,
                'description' => $item->description,
                'color' => $item->color,
                'presentation_label' => $item->productVariant?->presentation_label,
                'quantity' => $this->formatQuantity((string) $item->quantity),
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
        ];
    }

    /**
     * El área se captura como texto libre: solo un número entero se formatea con miles («5.750»);
     * cualquier otro texto se muestra tal cual lo escribió el comercial.
     */
    private function formatArea(?string $area): string
    {
        $area = trim((string) $area);

        if ($area === '') {
            return self::NOT_APPLICABLE;
        }

        return ctype_digit($area) ? $this->groupThousands($area) : $area;
    }

    /**
     * El espesor también es texto libre: la unidad se agrega solo si escribieron un número,
     * para no imprimir «4 Mils Mils».
     */
    private function formatThickness(?string $thickness): string
    {
        $thickness = trim((string) $thickness);

        if ($thickness === '') {
            return self::NOT_APPLICABLE;
        }

        return preg_match('/^\d+([.,]\d+)?$/', $thickness) === 1 ? $thickness.' Mils' : $thickness;
    }

    /**
     * Cantidad con formato colombiano y decimales solo cuando los tiene: «55», «2,5», «1.200».
     * Se trabaja sobre el string del cast decimal para no pasar por float.
     */
    private function formatQuantity(string $quantity): string
    {
        [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $grouped = $this->groupThousands(ltrim($integer, '-') ?: '0');

        return (str_starts_with($integer, '-') ? '-' : '').$grouped.($fraction !== '' ? ','.$fraction : '');
    }

    private function groupThousands(string $digits): string
    {
        return strrev(implode('.', str_split(strrev($digits), 3)));
    }

    private function signatureBase64(?User $user): ?string
    {
        $path = $user?->signature_path;
        $disk = Storage::disk(User::SIGNATURE_DISK);

        if (blank($path) || ! $disk->fileExists($path)) {
            return null;
        }

        $mimeType = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode((string) $disk->get($path));
    }
}
