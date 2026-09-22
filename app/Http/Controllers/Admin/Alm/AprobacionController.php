<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\FirmasDocumentoRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\FirmaDocumento;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Qué firmas lleva al pie el formato impreso de cada documento de Almacén.
 *
 * No es un flujo de aprobación: nada queda detenido esperando a nadie. Aquí
 * sólo se decide qué rayas trae la hoja, en qué orden y qué dice cada una.
 * Van por almacén porque quien firma en el general no es quien firma en obra;
 * el almacén que nadie tocó imprime la plantilla del enum.
 */
class AprobacionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('alm.aprobaciones.ver');

        return Inertia::render('admin/almacen/aprobaciones/index', [
            'almacenes' => Almacen::query()
                ->where('activo', true)
                ->orderBy('clave')
                ->get(['id', 'clave', 'nombre']),
            'documentos' => array_map(fn (DocumentoAlm $tipo): array => [
                'valor' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'ayuda' => $tipo->ayuda(),
                'tieneFormato' => $tipo->tieneFormato(),
                'porDefecto' => $tipo->firmasPorDefecto(),
            ], DocumentoAlm::cases()),
            'configuradas' => $this->configuradas(),
            'usuarios' => Usuario::query()->orderBy('name')->get(['id', 'name']),
            'puedeConfigurar' => request()->user()?->can('alm.aprobaciones.configurar') ?? false,
        ]);
    }

    /**
     * Se reemplazan las firmas del almacén entero, porque la pantalla se
     * guarda entera. Un documento sin renglones vuelve a la plantilla.
     */
    public function update(FirmasDocumentoRequest $request, Almacen $almacen): RedirectResponse
    {
        DB::transaction(function () use ($request, $almacen): void {
            FirmaDocumento::query()->where('almacen_id', $almacen->id)->delete();

            foreach ($request->array('documentos') as $documento) {
                foreach (array_values($documento['firmas'] ?? []) as $orden => $firma) {
                    $renglon = FirmaDocumento::query()->create([
                        'almacen_id' => $almacen->id,
                        'documento' => $documento['documento'],
                        'orden' => $orden + 1,
                        'rotulo' => trim((string) $firma['rotulo']),
                        'nombre' => trim((string) ($firma['nombre'] ?? '')) ?: null,
                    ]);

                    $renglon->usuarios()->sync($firma['usuarios'] ?? []);
                }
            }
        });

        return back()->with('success', "Firmas guardadas para el almacén {$almacen->clave}.");
    }

    /**
     * Lo configurado, agrupado como lo pide la pantalla: almacén → documento →
     * sus renglones en orden.
     *
     * @return array<int, array<string, list<array{rotulo: string, nombre: string|null, usuarios: list<string>}>>>
     */
    private function configuradas(): array
    {
        return FirmaDocumento::query()
            ->with('usuarios:id')
            ->orderBy('orden')
            ->get()
            ->groupBy('almacen_id')
            ->map(fn ($delAlmacen) => $delAlmacen
                ->groupBy(fn (FirmaDocumento $firma): string => $firma->documento->value)
                ->map(fn ($delDocumento) => $delDocumento
                    ->map(fn (FirmaDocumento $firma): array => [
                        'rotulo' => $firma->rotulo,
                        'nombre' => $firma->nombre,
                        'usuarios' => $firma->usuarios->pluck('id')->all(),
                    ])
                    ->values()
                    ->all())
                ->all())
            ->all();
    }
}
