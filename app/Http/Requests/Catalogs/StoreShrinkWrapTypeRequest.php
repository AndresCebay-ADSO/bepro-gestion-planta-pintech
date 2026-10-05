<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\RawMaterialType;
use App\Models\RawMaterial;
use App\Models\ShrinkWrapType;
use App\Rules\RawMaterialOfType;
use App\Rules\UniqueIgnoringCase;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tipo de termoencogido con su receta: qué empaque secundario gasta cada aplicación (3.8).
 */
class StoreShrinkWrapTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShrinkWrapType::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', new UniqueIgnoringCase('shrink_wrap_types', 'name', $this->currentType()?->id)],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:20'],
            'items.*.raw_material_id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('raw_materials', 'id'),
                new RawMaterialOfType(RawMaterialType::SecondaryPackaging),
                $this->activeOrAlreadyInRecipe(),
            ],
            // Tope de la columna decimal(12,4): `numeric` deja pasar «1e20», que no cabe en ella (#192).
            'items.*.quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('Agrega al menos una materia prima a la receta.'),
            'items.min' => __('Agrega al menos una materia prima a la receta.'),
            'items.*.raw_material_id.distinct' => __('No puedes repetir la misma materia prima en la receta.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('nombre'),
            'items' => __('receta'),
            'items.*.raw_material_id' => __('materia prima'),
            'items.*.quantity' => __('cantidad por aplicación'),
        ];
    }

    /**
     * El tipo que se edita; al crear no hay ninguno.
     */
    protected function currentType(): ?ShrinkWrapType
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        // Sin la clave `is_active`: al crear, activo; al editar, el que ya tenía. Un valor que no es booleano cuenta como
        // falso, como en los demás catálogos (el formulario siempre envía uno).
        $merge = ['is_active' => $this->boolean('is_active', $this->currentType()?->is_active ?? true)];

        // En planta se escribe «1,5»: la coma decimal se acepta, como en las fórmulas.
        if (is_array($this->input('items'))) {
            $merge['items'] = array_map(
                fn (mixed $item): mixed => is_array($item) && is_string($item['quantity'] ?? null)
                    ? [...$item, 'quantity' => str_replace(',', '.', trim($item['quantity']))]
                    : $item,
                $this->input('items'),
            );
        }

        $this->merge($merge);
    }

    /**
     * Una materia prima desactivada no se ofrece en recetas nuevas, pero la que ya estaba en la receta se conserva: si no,
     * desactivar una bolsa impediría editar el nombre del tipo que la usa.
     */
    private function activeOrAlreadyInRecipe(): Closure
    {
        // Misma regla que las opciones de la pantalla (RawMaterial::scopeSelectable). La receta actual se lee una vez.
        $recipeIds = $this->currentType()?->items()->pluck('raw_material_id')->all() ?? [];

        return function (string $attribute, mixed $value, Closure $fail) use ($recipeIds): void {
            if (! RawMaterial::query()->whereKey((int) $value)->selectable($recipeIds)->exists()) {
                $fail(__('La materia prima elegida está inactiva.'));
            }
        };
    }
}
