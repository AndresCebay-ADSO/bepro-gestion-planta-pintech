<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductionOrderPackagingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $product_variant_id
 * @property string $planned_units
 * @property string|null $actual_units
 * @property string|null $new_containers_used
 * @property int|null $package_raw_material_id
 * @property int|null $label_raw_material_id
 * @property string|null $labels_used
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read ProductVariant $productVariant
 * @property-read RawMaterial|null $packageRawMaterial
 * @property-read RawMaterial|null $labelRawMaterial
 */
#[Fillable([
    'production_order_id',
    'product_variant_id',
    'planned_units',
    'actual_units',
    'new_containers_used',
    'package_raw_material_id',
    'label_raw_material_id',
    'labels_used',
    'notes',
])]
class ProductionOrderPackagingPlan extends Model
{
    /** @use HasFactory<ProductionOrderPackagingPlanFactory> */
    use HasFactory;

    /**
     * Una presentación va una sola vez por orden: cada una deja un lote de PT, que se identifica por el número de lote de
     * la OP más la presentación (B55). Lo usan la validación y el controlador cuando dos peticiones chocan en el índice.
     */
    public const DUPLICATE_PRESENTATION_MESSAGE = 'Esta presentación ya está en el plan de envasado de la orden.';

    protected $table = 'production_order_packaging_plan';

    protected function casts(): array
    {
        return [
            'planned_units' => 'decimal:4',
            'actual_units' => 'decimal:4',
            'new_containers_used' => 'decimal:4',
            'labels_used' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Envase consumido, guardado al completar la OP (null mientras está abierta). */
    public function packageRawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'package_raw_material_id');
    }

    public function labelRawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'label_raw_material_id');
    }

    /**
     * Agrega una presentación al plan de envasado de una OP. Nace con la etiqueta habitual de la presentación si sigue
     * activa (una desactivada no se ofrece para registros nuevos); en la OP se puede cambiar.
     */
    public static function createForVariant(int $orderId, int $variantId, string|int|float $plannedUnits): self
    {
        return self::create([
            'production_order_id' => $orderId,
            'product_variant_id' => $variantId,
            'planned_units' => $plannedUnits,
            'label_raw_material_id' => RawMaterial::query()
                ->active()
                ->whereKey(ProductVariant::query()->whereKey($variantId)->select('label_raw_material_id'))
                ->value('id'),
        ]);
    }

    /**
     * Qué empaque consume la presentación al envasar $actualUnits: el envase de la presentación con sus envases nuevos y
     * la etiqueta del plan con sus etiquetas usadas. Vacío = tantos como unidades envasadas (los reutilizados no se
     * descuentan). La vista previa pasa en $requested lo que el operario aún no guardó; completar usa lo guardado.
     * Una sola regla para las dos: no pueden separarse.
     *
     * @param  array<string, mixed>  $requested
     * @return array{package_id: int|null, new_containers: string|null, label_id: int|null, labels_used: string|null}
     */
    public function packagingConsumption(string $actualUnits, array $requested = []): array
    {
        $value = fn (string $field, mixed $stored): mixed => array_key_exists($field, $requested) ? $requested[$field] : $stored;

        $packageId = $this->package_raw_material_id ?? $this->productVariant?->package_raw_material_id;
        $labelId = $value('label_raw_material_id', $this->label_raw_material_id);
        $newContainers = $value('new_containers_used', $this->new_containers_used);
        $labelsUsed = $value('labels_used', $this->labels_used);

        return [
            'package_id' => $packageId !== null ? (int) $packageId : null,
            'new_containers' => $packageId !== null ? (string) ($newContainers ?? $actualUnits) : null,
            'label_id' => $labelId !== null ? (int) $labelId : null,
            'labels_used' => $labelId !== null ? (string) ($labelsUsed ?? $actualUnits) : null,
        ];
    }
}
