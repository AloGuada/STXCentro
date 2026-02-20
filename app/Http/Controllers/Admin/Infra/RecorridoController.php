<?php

namespace App\Http\Controllers\Admin\Infra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Infra\BombaStoreRequest;
use App\Http\Requests\Admin\Infra\CompresorStoreRequest;
use App\Http\Requests\Admin\Infra\PtarStoreRequest;
use App\Http\Requests\Admin\Infra\TanqueStoreRequest;
use App\Http\Requests\Admin\Infra\TransformadorStoreRequest;
use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Ptar;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;
use App\Models\Infra\Turno;
use App\Services\Infra\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecorridoController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): Response
    {
        $fecha = $request->get('fecha', now()->toDateString());
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin = Carbon::parse($fecha)->endOfDay();

        // Turnos configurados para este día
        $turnos = Turno::paraFecha($fecha);

        // Si no hay turnos configurados, usar turno virtual
        if ($turnos->isEmpty()) {
            $turnoData = [[
                'turno' => ['id' => null, 'nombre' => 'Recorrido', 'hora_inicio' => null, 'hora_fin' => null, 'orden' => 0],
                'compresores' => Compresor::whereBetween('created_at', [$inicio, $fin])->whereNull('infra_turno_id')->latest()->first(),
                'bombas' => Bomba::whereBetween('created_at', [$inicio, $fin])->whereNull('infra_turno_id')->latest()->first(),
                'transformador' => Transformador::whereBetween('created_at', [$inicio, $fin])->whereNull('infra_turno_id')->latest()->first(),
                'tanques' => Tanque::whereBetween('created_at', [$inicio, $fin])->whereNull('infra_turno_id')->latest()->first(),
                'ptar' => Ptar::whereBetween('created_at', [$inicio, $fin])->whereNull('infra_turno_id')->latest()->first(),
            ]];
        } else {
            $turnoData = $turnos->map(fn (Turno $turno) => [
                'turno' => $turno,
                'compresores' => Compresor::whereBetween('created_at', [$inicio, $fin])->where('infra_turno_id', $turno->id)->latest()->first(),
                'bombas' => Bomba::whereBetween('created_at', [$inicio, $fin])->where('infra_turno_id', $turno->id)->latest()->first(),
                'transformador' => Transformador::whereBetween('created_at', [$inicio, $fin])->where('infra_turno_id', $turno->id)->latest()->first(),
                'tanques' => Tanque::whereBetween('created_at', [$inicio, $fin])->where('infra_turno_id', $turno->id)->latest()->first(),
                'ptar' => Ptar::whereBetween('created_at', [$inicio, $fin])->where('infra_turno_id', $turno->id)->latest()->first(),
            ])->all();
        }

        // Semáforos: última lectura global del día (sin filtrar por turno)
        $compresores = Compresor::whereBetween('created_at', [$inicio, $fin])->latest()->first();
        $bombas = Bomba::whereBetween('created_at', [$inicio, $fin])->latest()->first();
        $transformador = Transformador::whereBetween('created_at', [$inicio, $fin])->latest()->first();
        $tanques = Tanque::whereBetween('created_at', [$inicio, $fin])->latest()->first();
        $ptar = Ptar::whereBetween('created_at', [$inicio, $fin])->latest()->first();

        $year = (int) $request->get('year', Carbon::parse($fecha)->format('Y'));

        return Inertia::render('admin/infra/recorridos/index', [
            'fecha' => $fecha,
            'year' => $year,
            'turnoData' => $turnoData,
            'estados' => $this->dashboardService->evaluarEstados($compresores, $bombas, $tanques, $ptar, $transformador),
            'chartCompresores' => Inertia::defer(fn () => Compresor::horasLaboradasMensuales($year), 'charts'),
            'chartBombas' => Inertia::defer(fn () => Bomba::estadisticasMensuales($year), 'charts'),
            'chartTransformadores' => Inertia::defer(fn () => Transformador::consumosMensuales($year), 'charts'),
            'chartTanques' => Inertia::defer(fn () => Tanque::consumoMensualAcumulado($year), 'charts'),
        ]);
    }

    public function show(Request $request): Response
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());
        $turnoId = $request->get('turno_id');
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin = Carbon::parse($fecha)->endOfDay();

        $query = fn ($model) => $model::whereBetween('created_at', [$inicio, $fin])
            ->when($turnoId, fn ($q) => $q->where('infra_turno_id', $turnoId))
            ->when(! $turnoId, fn ($q) => $q->whereNull('infra_turno_id'))
            ->latest()
            ->first();

        $data = match ($sistema) {
            'compresores' => $query(Compresor::class),
            'bombas' => $query(Bomba::class),
            'transformadores' => $query(Transformador::class),
            'tanques' => $query(Tanque::class),
            'ptar' => $query(Ptar::class),
            default => null,
        };

        $page = match ($sistema) {
            'compresores' => 'admin/infra/recorridos/compresores',
            'bombas' => 'admin/infra/recorridos/bombas',
            'transformadores' => 'admin/infra/recorridos/transformadores',
            'tanques' => 'admin/infra/recorridos/tanques',
            'ptar' => 'admin/infra/recorridos/ptar',
            default => 'admin/infra/recorridos/index',
        };

        $turno = $turnoId ? Turno::find($turnoId) : null;

        return Inertia::render($page, [
            'data' => $data,
            'fecha' => $fecha,
            'turno' => $turno,
        ]);
    }

    public function create(Request $request): Response
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());
        $turnoId = $request->get('turno_id');

        $page = match ($sistema) {
            'compresores' => 'admin/infra/sistemas/compresores',
            'bombas' => 'admin/infra/sistemas/bombas',
            'transformadores' => 'admin/infra/sistemas/transformadores',
            'tanques' => 'admin/infra/sistemas/tanques',
            'ptar' => 'admin/infra/sistemas/ptar',
            default => 'admin/infra/recorridos/index',
        };

        $turno = $turnoId ? Turno::find($turnoId) : null;

        return Inertia::render($page, [
            'fecha' => $fecha,
            'turno' => $turno,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());
        $turnoId = $request->get('turno_id');

        match ($sistema) {
            'compresores' => $this->storeCompresor(app(CompresorStoreRequest::class), $fecha, $turnoId),
            'bombas' => $this->storeBomba(app(BombaStoreRequest::class), $fecha, $turnoId),
            'transformadores' => $this->storeTransformador(app(TransformadorStoreRequest::class), $fecha, $turnoId),
            'tanques' => $this->storeTanque(app(TanqueStoreRequest::class), $fecha, $turnoId),
            'ptar' => $this->storePtar(app(PtarStoreRequest::class), $fecha, $turnoId),
            default => null,
        };

        return to_route('admin.infra.recorridos.index', ['fecha' => $fecha]);
    }

    private function storeCompresor(CompresorStoreRequest $request, string $fecha, ?string $turnoId): void
    {
        $record = Compresor::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'infra_turno_id' => $turnoId,
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeBomba(BombaStoreRequest $request, string $fecha, ?string $turnoId): void
    {
        $record = Bomba::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'infra_turno_id' => $turnoId,
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeTransformador(TransformadorStoreRequest $request, string $fecha, ?string $turnoId): void
    {
        $record = Transformador::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'infra_turno_id' => $turnoId,
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeTanque(TanqueStoreRequest $request, string $fecha, ?string $turnoId): void
    {
        $record = Tanque::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'infra_turno_id' => $turnoId,
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storePtar(PtarStoreRequest $request, string $fecha, ?string $turnoId): void
    {
        $record = Ptar::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
            'infra_turno_id' => $turnoId,
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }
}
