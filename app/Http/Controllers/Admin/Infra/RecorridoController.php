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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecorridoController extends Controller
{
    public function index(Request $request): Response
    {
        $fecha = $request->get('fecha', now()->toDateString());
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin = Carbon::parse($fecha)->endOfDay();

        return Inertia::render('admin/infra/recorridos/index', [
            'fecha' => $fecha,
            'compresores' => Compresor::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'bombas' => Bomba::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'transformador' => Transformador::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'tanques' => Tanque::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'ptar' => Ptar::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
        ]);
    }

    public function show(Request $request): Response
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin = Carbon::parse($fecha)->endOfDay();

        $data = match ($sistema) {
            'compresores' => Compresor::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'bombas' => Bomba::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'transformadores' => Transformador::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'tanques' => Tanque::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
            'ptar' => Ptar::whereBetween('created_at', [$inicio, $fin])->latest()->first(),
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

        return Inertia::render($page, [
            'data' => $data,
            'fecha' => $fecha,
        ]);
    }

    public function create(Request $request): Response
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());

        $page = match ($sistema) {
            'compresores' => 'admin/infra/sistemas/compresores',
            'bombas' => 'admin/infra/sistemas/bombas',
            'transformadores' => 'admin/infra/sistemas/transformadores',
            'tanques' => 'admin/infra/sistemas/tanques',
            'ptar' => 'admin/infra/sistemas/ptar',
            default => 'admin/infra/recorridos/index',
        };

        return Inertia::render($page, [
            'fecha' => $fecha,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sistema = $request->get('sistema');
        $fecha = $request->get('fecha', now()->toDateString());

        match ($sistema) {
            'compresores' => $this->storeCompresor(app(CompresorStoreRequest::class), $fecha),
            'bombas' => $this->storeBomba(app(BombaStoreRequest::class), $fecha),
            'transformadores' => $this->storeTransformador(app(TransformadorStoreRequest::class), $fecha),
            'tanques' => $this->storeTanque(app(TanqueStoreRequest::class), $fecha),
            'ptar' => $this->storePtar(app(PtarStoreRequest::class), $fecha),
            default => null,
        };

        return to_route('admin.infra.recorridos.index', ['fecha' => $fecha]);
    }

    private function storeCompresor(CompresorStoreRequest $request, string $fecha): void
    {
        $record = Compresor::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeBomba(BombaStoreRequest $request, string $fecha): void
    {
        $record = Bomba::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeTransformador(TransformadorStoreRequest $request, string $fecha): void
    {
        $record = Transformador::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storeTanque(TanqueStoreRequest $request, string $fecha): void
    {
        $record = Tanque::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }

    private function storePtar(PtarStoreRequest $request, string $fecha): void
    {
        $record = Ptar::create([
            ...$request->validated(),
            'usuario_id' => auth()->id(),
        ]);
        $record->created_at = Carbon::parse($fecha);
        $record->save();
    }
}
