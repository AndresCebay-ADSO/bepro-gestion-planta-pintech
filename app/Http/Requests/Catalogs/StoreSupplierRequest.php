<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\ClientContactRules;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Supplier;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Proveedor: solo el nombre es obligatorio. El NIT y el teléfono siguen las mismas reglas que los del cliente.
 */
class StoreSupplierRequest extends FormRequest
{
    use ClientContactRules;
    use NormalizesText;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Supplier::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplierId = $this->currentSupplier()?->id;

        return [
            'name' => ['bail', 'required', 'string', 'max:'.Supplier::NAME_MAX_LENGTH, new UniqueIgnoringCase('suppliers', 'name', $supplierId)],
            'nit' => [...$this->nitRules(), Rule::unique('suppliers', 'nit')->ignore($supplierId)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => $this->phoneRules(),
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:'.Supplier::NOTES_MAX_LENGTH],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * El proveedor que se edita; al crear no hay ninguno.
     */
    protected function currentSupplier(): ?Supplier
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        // Sin la clave `is_active`: al crear, activo; al editar, el que ya tenía.
        $this->merge([
            'is_active' => $this->boolean('is_active', $this->currentSupplier()?->is_active ?? true),
            ...$this->normalizedText(['name' => TextFormat::Name, 'nit' => TextFormat::Name]),
        ]);
    }
}
