<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\SignatureOptimizerService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, SignatureOptimizerService $optimizer): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->safe()->except(['signature', 'remove_signature']);
        $validated['phone'] = ! empty($validated['phone']) ? $validated['phone'] : null;
        $validated['job_title'] = ! empty($validated['job_title']) ? $validated['job_title'] : null;

        $oldSignatureToDelete = null;
        $newSignaturePath = null;

        if ($request->hasFile('signature')) {
            $newSignaturePath = $optimizer->optimizeAndStore($request->file('signature'));
            $oldSignatureToDelete = $user->signature_path;
            $validated['signature_path'] = $newSignaturePath;
        } elseif ($request->boolean('remove_signature')) {
            $oldSignatureToDelete = $user->signature_path;
            $validated['signature_path'] = null;
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                // El correo no cambia desde el perfil (D-USR1): solo llega el mismo, a lo sumo con otras mayúsculas, y no
                // hay nada que volver a verificar.
                $user->fill($validated);
                $user->save();
            });
        } catch (\Throwable $e) {
            if ($newSignaturePath) {
                Storage::disk(User::SIGNATURE_DISK)->delete($newSignaturePath);
            }

            throw $e;
        }

        if ($oldSignatureToDelete) {
            Storage::disk(User::SIGNATURE_DISK)->delete($oldSignatureToDelete);
        }

        return to_route('profile.edit');
    }
}
