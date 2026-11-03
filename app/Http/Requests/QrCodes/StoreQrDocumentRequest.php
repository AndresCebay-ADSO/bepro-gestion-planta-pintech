<?php

declare(strict_types=1);

namespace App\Http\Requests\QrCodes;

use App\Enums\QrDocumentType;
use App\Models\QrCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreQrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $qrCode = $this->route('qrCode');

        return $qrCode instanceof QrCode
            && ($this->user()?->can('manageDocuments', $qrCode) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'document' => QrDocumentType::fileRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document' => 'certificado',
        ];
    }
}
