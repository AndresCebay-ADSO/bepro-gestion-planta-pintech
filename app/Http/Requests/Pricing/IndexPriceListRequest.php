<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class IndexPriceListRequest extends FormRequest
{
    /**
     * La lista de precios se calcula en vivo desde productos y presentaciones (`PriceListService`); no hay un modelo
     * propio sobre el cual poner una policy, así que se pide el permiso directo.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::PriceListsView->value) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
