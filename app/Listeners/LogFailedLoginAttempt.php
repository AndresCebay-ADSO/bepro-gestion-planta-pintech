<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

class LogFailedLoginAttempt
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected Request $request
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'Unknown';

        activity('auth')
            ->event('failed_login')
            ->withProperties([
                'email' => $email,
                'ip' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ])
            ->log("Intento de inicio de sesión fallido: {$email}");
    }
}
