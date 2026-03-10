<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\DriveLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DriveAuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('externo')->check()) {
            return redirect()->intended(route('drive.dashboard'));
        }

        return Inertia::render('drive/auth/login');
    }

    public function login(DriveLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('externo')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $externo = Auth::guard('externo')->user();

        if (! $externo->activo) {
            Auth::guard('externo')->logout();

            return back()->withErrors([
                'email' => 'Su cuenta está desactivada. Contacte al administrador.',
            ])->onlyInput('email');
        }

        $externo->update(['ultimo_acceso' => now()]);

        $request->session()->regenerate();

        return to_route('drive.dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('externo')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return to_route('drive.login');
    }
}
