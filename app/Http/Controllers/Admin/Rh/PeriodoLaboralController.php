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
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
            ->with(['persona.datosExtra', 'persona.foto', 'persona.periodosLaborales.puesto', 'persona.documentos.media', 'puesto.departamento', 'requisicion'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_empleado', 'ilike', "%{$search}%")
                        ->orWhere('estado', 'ilike', "%{$search}%")
                        ->orWhereHas('persona', fn ($pq) => $pq
                            ->where('nombre', 'ilike', "%{$search}%")
                            ->orWhere('apellido', 'ilike', "%{$search}%")
                        )
                        ->orWhereHas('puesto', fn ($pq) => $pq
                            ->where('nombre', 'ilike', "%{$search}%")
                            ->orWhereHas('departamento', fn ($dq) => $dq
                                ->where('descripcion', 'ilike', "%{$search}%")
                            )
                        )
                        ->orWhereHas('requisicion', fn ($rq) => $rq
                            ->where('folio', 'ilike', "%{$search}%")
                        );
                });
            })
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->sort_by, function ($query) use ($request) {
                $dir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

                return match ($request->sort_by) {
                    'persona' => $query
                        ->leftJoin('rh_personas', 'rh_periodos_laborales.persona_id', '=', 'rh_personas.id')
                        ->orderBy('rh_personas.nombre', $dir)
                        ->select('rh_periodos_laborales.*'),
                    'puesto' => $query
                        ->leftJoin('rh_puestos', 'rh_periodos_laborales.puesto_id', '=', 'rh_puestos.id')
                        ->orderBy('rh_puestos.nombre', $dir)
                        ->select('rh_periodos_laborales.*'),
                    'departamento' => $query
                        ->leftJoin('rh_puestos as rp_dep', 'rh_periodos_laborales.puesto_id', '=', 'rp_dep.id')
                        ->leftJoin('departamentos', 'rp_dep.departamento_id', '=', 'departamentos.id')
                        ->orderBy('departamentos.descripcion', $dir)
                        ->select('rh_periodos_laborales.*'),
                    default => $query->orderBy($request->sort_by, $dir),
                };
            }, fn ($query) => $query->orderBy('fecha_inicio'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/periodos-laborales/index', [
            'periodos' => $periodos,
            'filters' => $request->only('search', 'estado', 'sort_by', 'sort_dir'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('rh.periodos-laborales.crear');

        $requisiciones = Requisicion::where(function ($q) use ($request) {
            $q->whereIn('estado', ['abierta', 'en_proceso']);
            if ($request->filled('requisicion_id')) {
                $q->orWhere('id', $request->query('requisicion_id'));
            }
        })->orderByDesc('fecha_creacion')->get(['id', 'folio', 'puesto_id', 'tipo_contrato_generado', 'salario']);

        return Inertia::render('admin/rh/periodos-laborales/create', [
            'personas' => Persona::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
            'requisiciones' => $requisiciones,
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

        $periodoLaboral->load(['persona.datosExtra', 'puesto', 'onboarding.tareas.responsable.persona', 'onboarding.tareas.media', 'requisicion']);

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
            'estado' => 'baja',
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
            $fechaInicio->locale('es')->translatedFormat('d \d\e F \d\e Y')
        );

        $fechaVencimiento = mb_strtoupper(
            $fechaInicio->copy()->addDays(91)->locale('es')->translatedFormat('d \d\e F \d\e Y')
        );

        $sexo = mb_strlen($curp) > 10 ? mb_strtoupper(mb_substr($curp, 10, 1)) : '';
        $lugarNacimiento = mb_strlen($curp) > 12 ? mb_strtoupper(mb_substr($curp, 11, 2)) : '';

        $fechaNacimiento = $persona->fecha_nacimiento
            ? Carbon::parse($persona->fecha_nacimiento)->format('d/m/Y')
            : '';

        $edad = $persona->fecha_nacimiento
            ? Carbon::parse($persona->fecha_nacimiento)->age
            : '';

        $salarioDiario = $periodoLaboral->salario_diario
            ? number_format((float) $periodoLaboral->salario_diario, 2)
            : '';
        $sueldoMensual = $periodoLaboral->sueldo_mensual
            ? number_format((float) $periodoLaboral->sueldo_mensual, 2)
            : '';

        $pdf = Pdf::loadView('pdf.rh.contrato-laboral', [
            'periodo' => $periodoLaboral,
            'persona' => $persona,
            'extras' => $extras,
            'puesto' => $periodoLaboral->puesto?->nombre ?? '',
            'departamento' => $periodoLaboral->puesto?->departamento?->descripcion ?? '',
            'salarioDiario' => $salarioDiario,
            'sueldoMensual' => $sueldoMensual,
            'fechaIngreso' => $fechaIngreso,
            'fechaIngresoLarga' => $fechaIngresoLarga,
            'fechaVencimiento' => $fechaVencimiento,
            'fechaNacimiento' => $fechaNacimiento,
            'sexo' => $sexo,
            'lugarNacimiento' => $lugarNacimiento,
            'edad' => $edad,
            'numeroEmpleado' => $periodoLaboral->numero_empleado ?? '',
            'tipoContrato' => $periodoLaboral->requisicion?->tipo_contrato_generado ?? $periodoLaboral->tipo_contrato ?? 'planta',
        ])->setPaper('letter', 'portrait');

        $filename = 'contrato-'.$persona->nombre.'-'.$persona->apellido.'.pdf';

        return $pdf->stream($filename);
    }

    public function generarGafetePdf(PeriodoLaboral $periodoLaboral): HttpResponse
    {
        $this->authorize('rh.periodos-laborales.ver');

        $periodoLaboral->load(['persona.foto', 'puesto']);

        $persona = $periodoLaboral->persona;

        $qrBase64 = $this->generarQrBase64($periodoLaboral, $persona);

        $fotoPath = null;
        if ($persona->foto?->path) {
            $fullPath = storage_path('app/public/'.$persona->foto->path);
            if (file_exists($fullPath)) {
                $fotoPath = $fullPath;
            }
        }

        $pdf = Pdf::loadView('pdf.rh.gafete', [
            'persona' => $persona,
            'puesto' => $periodoLaboral->puesto?->nombre ?? '',
            'numero' => str_pad($periodoLaboral->id, 4, '0', STR_PAD_LEFT),
            'telefono' => $persona->telefono ?? '',
            'qrBase64' => $qrBase64,
            'fotoPath' => $fotoPath,
        ]);

        $filename = 'gafete-'.$persona->nombre.'-'.$persona->apellido.'.pdf';

        return $pdf->stream($filename);
    }

    public function generarTarjetaPdf(PeriodoLaboral $periodoLaboral): HttpResponse
    {
        $this->authorize('rh.periodos-laborales.ver');

        $periodoLaboral->load(['persona', 'puesto']);

        $persona = $periodoLaboral->persona;

        $qrBase64 = $this->generarQrBase64($periodoLaboral, $persona);

        $pdf = Pdf::loadView('pdf.rh.tarjeta', [
            'persona' => $persona,
            'puesto' => $periodoLaboral->puesto?->nombre ?? '',
            'numero' => str_pad($periodoLaboral->id, 4, '0', STR_PAD_LEFT),
            'telefono' => $persona->telefono ?? '',
            'qrBase64' => $qrBase64,
        ]);

        $filename = 'tarjeta-'.$persona->nombre.'-'.$persona->apellido.'.pdf';

        return $pdf->stream($filename);
    }

    private function generarQrBase64(PeriodoLaboral $periodoLaboral, Persona $persona): string
    {
        $qrData = json_encode([
            'id' => $periodoLaboral->id,
            'nombres' => $persona->nombre,
            'apellidos' => $persona->apellido,
        ]);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return base64_encode($writer->writeString($qrData));
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
