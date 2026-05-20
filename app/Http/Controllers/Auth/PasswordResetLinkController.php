<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

class PasswordResetLinkController extends FortifyController
{
    public function store(SendPasswordResetLinkRequest $request): Responsable
    {
        if (config('fortify.lowercase_usernames') && $request->has(Fortify::email())) {
            $request->merge([
                Fortify::email() => Str::lower($request->{Fortify::email()}),
            ]);
        }

        $status = $this->broker()->sendResetLink(
            $request->only(Fortify::email())
        );

        return match ($status) {
            Password::RESET_LINK_SENT => app(SuccessfulPasswordResetLinkRequestResponse::class, [
                'status' => $status,
            ]),
            Password::RESET_THROTTLED => throw ValidationException::withMessages([
                Fortify::email() => __('auth.reset_throttled'),
            ]),
            // Email no existe en la BD → respondemos como si sí existiera (no revelar info)
            // tap() ejecuta applyTimingDefense() como efecto secundario ANTES de retornar,
            // simulando el tiempo que tomaría enviar el correo real.
            default => tap(
                app(SuccessfulPasswordResetLinkRequestResponse::class, [
                    'status' => Password::RESET_LINK_SENT,
                ]),
                fn ($_) => $this->applyTimingDefense()
            ),

        };
    }

    protected function applyTimingDefense(): void
    {
        Hash::check(Str::random(40), Hash::make(Str::random(40)));
    }
}
