<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'users' => ['bail', 'required', 'array', 'min:1'],
            'users.*.user_id' => ['bail', 'required', 'integer', Rule::exists('users', 'id')],
            'users.*.is_default' => ['sometimes', 'boolean'],
        ];
    }
}
