<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\QrDocumentType;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreProductDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('manageDocuments', $product) ?? false);
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
                // `product_documents.file_name` es `varchar(255)` y guarda el nombre original (PRD-04).
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && mb_strlen($value->getClientOriginalName()) > 255) {
                        $fail(__('El nombre del archivo no puede pasar de 255 caracteres.'));
                    }
                },
            ],
        ];
    }
}
