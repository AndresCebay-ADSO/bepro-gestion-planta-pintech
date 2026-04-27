<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

class PasswordResetLinkController extends FortifyController
{
    /**
     * Send a reset link to the given user.
     */
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

        if ($status !== Password::RESET_LINK_SENT) {
            $this->applyTimingDefense();
        }

        return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => Password::RESET_LINK_SENT]);
    }

    /**
     * Apply a small timing defense for unknown users.
     */
    protected function applyTimingDefense(): void
    {
        Hash::check(Str::random(40), '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
    }
}
