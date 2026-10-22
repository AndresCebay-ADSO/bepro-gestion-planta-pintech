<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->profileRules($this->user()->id), [
            // El correo no se cambia desde el perfil (D-USR1): un administrador lo hace desde Usuarios. Cambiarlo aquí
            // dejaba al usuario sin verificar y fuera del sistema. Se llega con el mismo correo (el formulario lo
            // reenvía), sin distinguir mayúsculas.
            'email' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (mb_strtolower((string) $value) !== mb_strtolower((string) $this->user()->email)) {
                    $fail(__('Para cambiar tu correo, pídelo a un administrador.'));
                }
            }],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->signatureMessages();
    }
}
