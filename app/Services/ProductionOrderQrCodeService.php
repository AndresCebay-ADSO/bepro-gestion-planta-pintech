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
 *
 * Solo dos cosas cambian si está activo: un administrador (Códigos QR) y la cancelación de la orden. Ni la estampita
 * ni el certificado lo reactivan: con la orden abierta ya existe y alguien pudo cerrarlo a propósito.
 */
class ProductionOrderQrCodeService
{
    /**
     * Para el certificado: lo crea si no existe y actualiza la URL y el producto, sin tocar si está activo.
     */
    public function ensureForCertificate(ProductionOrder $order, int $userId): QrCode
    {
        $qrCode = $this->findOrCreate($order, $userId);

        $qrCode->fill([
            'product_id' => $order->product_id,
            'url' => route('qr.public.show', ['token' => $qrCode->token]),
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

    /**
     * Al cancelar la orden: quien escanee una estampita ya impresa no debe ver un lote que no existe (las estampitas se
     * desechan, decisión del 2026-10-02).
     */
    public function deactivateFor(ProductionOrder $order): void
    {
        QrCode::query()->where('production_order_id', $order->id)->update(['is_active' => false]);
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
        } catch (UniqueConstraintViolationException $exception) {
            // Dos impresiones a la vez (o una impresión y el certificado) crean el QR de la misma orden: la segunda choca
            // con el índice único y usa el que ganó. Si el choque fue del token (improbable), no hay QR y se reporta.
            return QrCode::query()->where('production_order_id', $order->id)->first() ?? throw $exception;
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
