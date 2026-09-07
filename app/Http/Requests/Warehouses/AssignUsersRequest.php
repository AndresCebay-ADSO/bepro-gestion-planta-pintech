<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && ($this->user()?->can('update', $warehouse) ?? false);
    }

    public function rules(): array
    {
        return [
            'users' => ['bail', 'required', 'array', 'min:1'],
            'users.*.user_id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'users.*.is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'users.required' => 'Debes seleccionar al menos un usuario para asignar al almacén.',
            'users.min' => 'Debes seleccionar al menos un usuario para asignar al almacén.',
            'users.*.user_id.required' => 'El usuario es obligatorio.',
            'users.*.user_id.distinct' => 'No puedes asignar el mismo usuario más de una vez.',
            'users.*.user_id.exists' => 'El usuario seleccionado no existe o no se encuentra activo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'users' => 'usuarios',
            'users.*.user_id' => 'usuario',
            'users.*.is_default' => 'almacén predeterminado',
        ];
    }
}
