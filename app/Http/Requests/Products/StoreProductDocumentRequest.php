<?php

namespace App\Http\Requests\Products;

use App\Enums\QrDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                Rule::in([
                    QrDocumentType::TechnicalDataSheet->value,
                    QrDocumentType::SafetyDataSheet->value,
                ]),
            ],
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'extensions:pdf',
                'max:10240',
            ],
        ];
    }
}
