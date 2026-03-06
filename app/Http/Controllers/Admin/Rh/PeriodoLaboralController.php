<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\PeriodoLaboralStoreRequest;
use App\Http\Requests\Admin\Rh\PeriodoLaboralUpdateRequest;
use App\Models\Rh\Onboarding;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requisicion;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PeriodoLaboralController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.periodos-laborales.ver');

        $periodos = PeriodoLaboral::query()
            ->with(['persona', 'puesto', 'requisicion'])
            ->when($request->search, fn ($q, $s) => $q
                ->whereHas('persona', fn ($pq) => $pq
                    ->where('nombre', 'like', "%{$s}%")
                    ->orWhere('apellido', 'like', "%{$s}%")
                )
            )
            ->orderByDesc('fecha_inicio')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/periodos-laborales/index', [
            'periodos' => $periodos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.periodos-laborales.crear');

        return Inertia::render('admin/rh/periodos-laborales/create', [
            'personas' => Persona::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
            'requisiciones' => Requisicion::whereIn('estado', ['abierta', 'en_proceso'])->orderByDesc('fecha_creacion')->get(['id', 'folio', 'puesto_id']),
        ]);
    }

    public function store(PeriodoLaboralStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.periodos-laborales.crear');

        PeriodoLaboral::create($request->validated());

        return to_route('admin.rh.periodos-laborales.index');
    }

    public function edit(PeriodoLaboral $periodoLaboral): Response
    {
        $this->authorize('rh.periodos-laborales.editar');

        $periodoLaboral->load(['persona', 'puesto', 'onboarding.tareas.responsable.persona', 'onboarding.tareas.media', 'requisicion']);

        $periodosActivos = PeriodoLaboral::query()
            ->where('estado', 'activo')
            ->with('persona')
            ->get()
            ->map(fn (PeriodoLaboral $p) => [
                'id' => $p->id,
                'nombre' => $p->persona->nombre.' '.$p->persona->apellido,
            ]);

        return Inertia::render('admin/rh/periodos-laborales/edit', [
            'periodo' => $periodoLaboral,
            'personas' => Persona::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
            'requisiciones' => Requisicion::orderByDesc('fecha_creacion')->get(['id', 'folio', 'puesto_id']),
            'periodosActivos' => $periodosActivos,
        ]);
    }

    public function update(PeriodoLaboralUpdateRequest $request, PeriodoLaboral $periodoLaboral): RedirectResponse
    {
        $this->authorize('rh.periodos-laborales.editar');

        $periodoLaboral->update($request->validated());

        return to_route('admin.rh.periodos-laborales.index');
    }

    public function destroy(PeriodoLaboral $periodoLaboral): RedirectResponse
    {
        $this->authorize('rh.periodos-laborales.eliminar');

        $periodoLaboral->delete();

        return to_route('admin.rh.periodos-laborales.index');
    }

    public function terminar(PeriodoLaboral $periodoLaboral): RedirectResponse
    {
        $this->authorize('rh.periodos-laborales.editar');

        $periodoLaboral->update([
            'estado' => 'terminado',
            'fecha_fin' => now(),
        ]);

        return back();
    }

    public function generarContratoPdf(PeriodoLaboral $periodoLaboral): HttpResponse
    {
        $this->authorize('rh.periodos-laborales.ver');

        $periodoLaboral->load(['persona.datosExtra', 'puesto.departamento', 'requisicion']);

        $persona = $periodoLaboral->persona;
        $extras = $persona->datosExtra;
        $curp = $extras->curp ?? '';

        $fechaInicio = $periodoLaboral->fecha_inicio
            ? Carbon::parse($periodoLaboral->fecha_inicio)
            : now();

        $fechaIngreso = $fechaInicio->format('d/m/Y');

        $fechaIngresoLarga = mb_strtoupper(
            $fechaInicio->translatedFormat('d \d\e F \d\e Y')
        );

        $fechaVencimiento = mb_strtoupper(
            $fechaInicio->copy()->addDays(91)->translatedFormat('d \d\e F \d\e Y')
        );

        $sexo = mb_strlen($curp) > 10 ? mb_strtoupper(mb_substr($curp, 10, 1)) : '';
        $lugarNacimiento = mb_strlen($curp) > 12 ? mb_strtoupper(mb_substr($curp, 11, 2)) : '';

        $fechaNacimiento = $persona->fecha_nacimiento
            ? Carbon::parse($persona->fecha_nacimiento)->format('d/m/Y')
            : '';

        $edad = $persona->fecha_nacimiento
            ? Carbon::parse($persona->fecha_nacimiento)->age
            : '';

        $pdf = Pdf::loadView('pdf.rh.contrato-laboral', [
            'periodo' => $periodoLaboral,
            'persona' => $persona,
            'extras' => $extras,
            'puesto' => $periodoLaboral->puesto?->nombre ?? '',
            'departamento' => $periodoLaboral->puesto?->departamento?->nombre ?? '',
            'salario' => $periodoLaboral->salario ? number_format((float) $periodoLaboral->salario, 2) : '',
            'fechaIngreso' => $fechaIngreso,
            'fechaIngresoLarga' => $fechaIngresoLarga,
            'fechaVencimiento' => $fechaVencimiento,
            'fechaNacimiento' => $fechaNacimiento,
            'sexo' => $sexo,
            'lugarNacimiento' => $lugarNacimiento,
            'edad' => $edad,
            'tipoContrato' => $periodoLaboral->requisicion?->tipo_contrato_generado ?? $periodoLaboral->tipo_contrato ?? 'planta',
        ])->setPaper('letter', 'portrait');

        $filename = 'contrato-'.$persona->nombre.'-'.$persona->apellido.'.pdf';

        return $pdf->download($filename);
    }

    public function crearOnboarding(PeriodoLaboral $periodoLaboral): RedirectResponse
    {
        $this->authorize('rh.onboarding.crear');

        if ($periodoLaboral->onboarding) {
            return back()->withErrors(['onboarding' => 'Este periodo ya tiene un onboarding asociado.']);
        }

        Onboarding::create([
            'periodo_id' => $periodoLaboral->id,
            'fecha_inicio' => now(),
            'progreso' => 0,
        ]);

        return back();
    }
}
