<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('proveedor')->check()) {
            return redirect()->intended(route('portal.dashboard'));
        }

        return Inertia::render('portal/auth/login');
    }

    public function login(PortalLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('proveedor')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $proveedor = Auth::guard('proveedor')->user();

        if (! $proveedor->tiene_acceso_portal || ! $proveedor->activo) {
            Auth::guard('proveedor')->logout();

            return back()->withErrors([
                'email' => 'Su cuenta no tiene acceso al portal o está desactivada.',
            ])->onlyInput('email');
        }

        $proveedor->update(['portal_ultimo_acceso' => now()]);

        $request->session()->regenerate();

        return to_route('portal.dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('proveedor')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return to_route('portal.login');
    }
}
