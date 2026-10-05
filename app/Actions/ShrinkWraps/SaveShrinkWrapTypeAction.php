<?php

declare(strict_types=1);

namespace App\Actions\ShrinkWraps;

use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use Illuminate\Support\Facades\DB;

/**
 * Crea o edita un tipo de termoencogido con su receta (3.8).
 *
 * La receta se sincroniza línea por línea (una materia prima aparece una sola vez por tipo) en vez de borrarla y crearla
 * de nuevo: así la auditoría registra qué materia prima entró, salió o cambió de cantidad. Los registros de termoencogido
 * guardan su propia copia de la receta, de modo que editarla no cambia el historial.
 */
class SaveShrinkWrapTypeAction
{
    /**
     * @param  array{name: string, is_active: bool, items: list<array{raw_material_id: int|string, quantity: int|float|string}>}  $validated
     */
    public function execute(array $validated, ?ShrinkWrapType $type = null): ShrinkWrapType
    {
        return DB::transaction(function () use ($validated, $type): ShrinkWrapType {
            $type = $type !== null
                ? ShrinkWrapType::query()->lockForUpdate()->findOrFail($type->id)
                : new ShrinkWrapType;

            $type->fill([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
            ])->save();

            $quantities = collect($validated['items'])
                ->mapWithKeys(fn (array $item): array => [(int) $item['raw_material_id'] => (string) $item['quantity']]);

            $current = $type->items()->get()->keyBy('raw_material_id');

            $current
                ->reject(fn (ShrinkWrapTypeItem $item): bool => $quantities->has($item->raw_material_id))
                ->each(fn (ShrinkWrapTypeItem $item) => $item->delete());

            foreach ($quantities as $rawMaterialId => $quantity) {
                $item = $current->get($rawMaterialId);

                if ($item === null) {
                    $type->items()->create(['raw_material_id' => $rawMaterialId, 'quantity' => $quantity]);

                    continue;
                }

                $item->update(['quantity' => $quantity]);
            }

            return $type->load('items');
        });
    }
}
