<?php

declare(strict_types=1);

namespace App\Http\Requests\RawMaterials;

use App\Actions\RawMaterials\UpdateRawMaterialAction;
use App\Enums\Permission;
use App\Enums\RawMaterialType;
use App\Http\Requests\Concerns\CatalogSelectionRules;
use App\Http\Requests\RawMaterials\Concerns\ManualReferencePriceRules;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Services\RawMaterialUsageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRawMaterialRequest extends FormRequest
{
    use CatalogSelectionRules;
    use ManualReferencePriceRules;

    public function authorize(): bool
    {
        $rawMaterial = $this->route('raw_material');

        return $rawMaterial instanceof RawMaterial
            && ($this->user()?->can('update', $rawMaterial) ?? false);
    }

    public function rules(): array
    {
        $rawMaterial = $this->route('raw_material');
        $rawMaterialId = is_object($rawMaterial) ? $rawMaterial->id : $rawMaterial;

        return [
            'code' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('raw_materials', 'code')->ignore($rawMaterialId),
            ],
            'category_id' => $this->activeOrCurrentRules('raw_material_categories', 'category_id', $this->route('raw_material')?->category_id),
            'unit_of_measure_id' => $this->activeOrCurrentRules('unit_of_measures', 'unit_of_measure_id', $this->route('raw_material')?->unit_of_measure_id),
            'minimum_stock' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999'],
            'alert_days_before_expiry' => ['bail', 'required', 'integer', 'min:0'],
            'price_variation_threshold' => ['bail', 'nullable', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'tracks_inventory' => ['sometimes', 'boolean'],
            'current_price' => $this->manualPriceRules(),
            'confirm_tracking_change' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cambiar de categoría puede cambiar el tipo de insumo. Si la materia prima ya se usa en algo que depende de su tipo
     * (el envase de una presentación, una línea de fórmula), pasarla a otro tipo dejaría ese uso incoherente: la
     * presentación o la fórmula ya no podrían editarse. Moverla a otra categoría del mismo tipo siempre se puede.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $material = $this->route('raw_material');

                if ($material instanceof RawMaterial) {
                    if (! $this->validateInventoryTracking($validator, $material)) {
                        return;
                    }

                    $tracksInventory = $this->has('tracks_inventory') ? $this->boolean('tracks_inventory') : $material->tracks_inventory;
                    // Solo al quitarle el control hace falta precio: una que ya no lo tenía conserva el suyo si no se envía.
                    $this->validateManualPrice($validator, $tracksInventory, priceRequired: ! $tracksInventory && $material->tracks_inventory);
                }
            },
            function (Validator $validator): void {
                $material = $this->route('raw_material');

                if (! $material instanceof RawMaterial || $validator->errors()->has('category_id')) {
                    return;
                }

                $newCategory = RawMaterialCategory::query()->find((int) $this->input('category_id'));

                if ($newCategory === null || $newCategory->id === $material->category_id) {
                    return;
                }

                // Sin categoría cuenta como Químico, igual que en RawMaterial::scopeUsableInFormulas().
                $currentType = $material->category?->type ?? RawMaterialType::Chemical;

                if ($newCategory->type !== $currentType) {
                    $message = app(RawMaterialUsageService::class)->typeChangeBlocker($material, $currentType, $newCategory->type);

                    if ($message !== null) {
                        $validator->errors()->add('category_id', $message);
                    }
                }
            },
        ];
    }

    /**
     * Cambiar el control de inventario cambia cómo se costea y qué exige producir, así que:
     * - solo lo cambia quien maneja costos (sin control, el precio se escribe a mano);
     * - no se quita con saldo en bodega: quedaría congelado, nada lo volvería a descontar;
     * - activarlo con OP abiertas que la usan pide confirmación: desde ahí necesita saldo, y no lo tiene, así que esas
     *   OP no se podrán completar hasta registrar compras (que sin control no se pueden registrar: por eso se confirma y
     *   no se bloquea).
     *
     * Devuelve false si el cambio no procede y no tiene sentido seguir validando el precio.
     */
    private function validateInventoryTracking(Validator $validator, RawMaterial $material): bool
    {
        if (! $this->has('tracks_inventory') || $this->boolean('tracks_inventory') === $material->tracks_inventory) {
            return true;
        }

        if (! ($this->user()?->can(Permission::CostsUpdate->value) ?? false)) {
            $validator->errors()->add('tracks_inventory', __(self::TRACKING_PERMISSION_MESSAGE));

            return false;
        }

        if (! $this->boolean('tracks_inventory')) {
            $hasStock = InventoryBatch::query()
                ->where('raw_material_id', $material->id)
                ->where('remaining_quantity', '>', 0)
                ->exists();

            if ($hasStock) {
                $validator->errors()->add('tracks_inventory', __(UpdateRawMaterialAction::STOCK_BLOCKS_UNTRACKING_MESSAGE));

                return false;
            }

            return true;
        }

        $openOrders = app(RawMaterialUsageService::class)->openProductionOrdersCount($material);

        if ($openOrders > 0 && ! $this->boolean('confirm_tracking_change')) {
            $validator->errors()->add('confirm_tracking_change', trans_choice(
                'Confirma: :count orden de producción abierta usa esta materia prima y, sin saldo, no se podrá completar hasta registrar compras.|Confirma: :count órdenes de producción abiertas usan esta materia prima y, sin saldo, no se podrán completar hasta registrar compras.',
                $openOrders,
                ['count' => $openOrders],
            ));

            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'category_id' => $this->input('category_id'),
            'minimum_stock' => $this->input('minimum_stock', 0),
            'alert_days_before_expiry' => $this->input('alert_days_before_expiry', 30),
            'price_variation_threshold' => $this->nullablePriceVariationThreshold(),
            'is_active' => $this->boolean('is_active', true),
        ];

        if ($this->has('tracks_inventory')) {
            $prepared['tracks_inventory'] = $this->boolean('tracks_inventory');
        }

        $this->merge($prepared);
    }

    private function nullablePriceVariationThreshold(): ?string
    {
        $value = $this->input('price_variation_threshold');

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
