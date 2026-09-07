<?php

declare(strict_types=1);

namespace App\Http\Requests\QrCodes;

use App\Models\QrCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $qrCode = $this->route('qrCode');

        return $qrCode instanceof QrCode
            ? ($this->user()?->can('update', $qrCode) ?? false)
            : ($this->user()?->can('update', QrCode::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'estado activo',
        ];
    }
}
