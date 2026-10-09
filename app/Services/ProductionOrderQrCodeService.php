<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\QrCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El QR de una orden: uno por orden (`qr_codes.production_order_id` es único), creado por quien lo necesite primero.
 * La estampita lo crea en la primera impresión y el certificado de calidad reutiliza el mismo token al completar, así
 * que el QR ya pegado en el envase lleva al certificado sin reimprimir.
 */
class ProductionOrderQrCodeService
{
    /**
     * Para el certificado: lo crea si no existe y lo deja activo con la URL y el producto actuales.
     */
    public function ensureActive(ProductionOrder $order, int $userId): QrCode
    {
        $qrCode = $this->findOrCreate($order, $userId);

        $qrCode->fill([
            'product_id' => $order->product_id,
            'url' => route('qr.public.show', ['token' => $qrCode->token]),
            'is_active' => true,
        ]);
        $qrCode->save();

        return $qrCode;
    }

    /**
     * Para la estampita: lo crea si no existe, pero no reactiva uno que un administrador desactivó; la estampita
     * llevaría un QR que da 404 o reabriría uno cerrado a propósito.
     *
     * @throws \DomainException si el QR de la orden está desactivado.
     */
    public function ensureForLabels(ProductionOrder $order, int $userId): QrCode
    {
        $qrCode = $this->findOrCreate($order, $userId);

        if (! $qrCode->is_active) {
            throw new \DomainException('El QR de este lote está desactivado. Actívalo en Códigos QR para imprimir estampitas.');
        }

        return $qrCode;
    }

    private function findOrCreate(ProductionOrder $order, int $userId): QrCode
    {
        $existing = QrCode::query()->where('production_order_id', $order->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $token = $this->generateToken();
        $qrCode = new QrCode([
            'production_order_id' => $order->id,
            'product_id' => $order->product_id,
            'token' => $token,
            'url' => route('qr.public.show', ['token' => $token]),
            'is_active' => true,
            'created_by' => $userId,
        ]);

        try {
            // En su propia transacción: si choca, PostgreSQL revierte solo esta inserción y la conexión sigue usable.
            DB::transaction(fn () => $qrCode->save());
        } catch (UniqueConstraintViolationException) {
            // Dos impresiones a la vez (o una impresión y el certificado) crean el QR de la misma orden: la segunda choca
            // con el índice único y usa el que ganó.
            return QrCode::query()->where('production_order_id', $order->id)->firstOrFail();
        }

        return $qrCode;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (QrCode::query()->where('token', $token)->exists());

        return $token;
    }
}
