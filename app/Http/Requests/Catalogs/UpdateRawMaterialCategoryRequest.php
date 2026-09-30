<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\RawMaterialCategory;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRawMaterialCategoryRequest extends StoreRawMaterialCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->route('raw_material_category');

        return $category instanceof RawMaterialCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('raw_material_category');

        return [
            'code' => ['bail', 'required', 'string', 'max:50', Rule::unique('raw_material_categories', 'code')->ignore($category?->id)],
            'name' => ['bail', 'required', 'string', 'max:100', new UniqueIgnoringCase('raw_material_categories', 'name', $category?->id)],
            ...$this->sharedRules(),
        ];
    }

    protected function defaultIsActive(): bool
    {
        $category = $this->route('raw_material_category');

        return $category instanceof RawMaterialCategory ? $category->is_active : true;
    }

    /**
     * El tipo decide dónde se ofrece cada materia prima (fórmulas, envases, etiquetas…). Cambiarlo con materias primas
     * dentro dejaría, por ejemplo, presentaciones con un envase que ya no es de tipo Envase.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('raw_material_category');

                if (! $category instanceof RawMaterialCategory || $validator->errors()->has('type') || ! $this->has('type')) {
                    return;
                }

                if ($this->input('type') === $category->type->value) {
                    return;
                }

                $materials = $category->rawMaterials()->count();

                if ($materials > 0) {
                    $validator->errors()->add('type', trans_choice(
                        'No se puede cambiar el tipo: la categoría tiene :count materia prima. Muévela a otra categoría primero.|No se puede cambiar el tipo: la categoría tiene :count materias primas. Muévelas a otra categoría primero.',
                        $materials,
                        ['count' => $materials],
                    ));
                }
            },
        ];
    }
}
