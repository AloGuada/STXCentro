<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Rh\PermisoAusencia;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PermisoPublicoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('rh/permisos');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'numero_empleado' => ['nullable', 'string', 'max:50'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'gerente' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'modalidad' => ['nullable', 'string', 'max:100'],
            'condicion' => ['nullable', 'string', 'max:100'],
            'razon' => ['nullable', 'string'],
            'fecha_permiso' => ['nullable', 'date'],
        ], [
            'nombres.required' => 'El nombre es requerido.',
            'apellidos.required' => 'Los apellidos son requeridos.',
        ]);

        $year = now()->year;
        $count = PermisoAusencia::whereYear('created_at', $year)->count() + 1;

        $permiso = PermisoAusencia::create([
            ...$validated,
            'folio' => 'PA-'.$year.'-'.str_pad($count, 4, '0', STR_PAD_LEFT),
            'fecha_elaboracion' => now(),
        ]);

        return back()->with('permiso', $permiso);
    }

    public function pdf(PermisoAusencia $permisoAusencia): View
    {
        $company = [
            'name' => config('app.company_name', 'Steelex'),
            'address' => config('app.company_address', ''),
            'phone' => config('app.company_phone', ''),
            'rfc' => config('app.company_rfc', ''),
            'website' => config('app.company_website', ''),
        ];

        return view('rh.permiso-pdf', [
            'permiso' => $permisoAusencia,
            'company' => $company,
        ]);
    }
}
