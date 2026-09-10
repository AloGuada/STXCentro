<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\InspeccionRequest;
use App\Models\Concepto;
use App\Models\Qal\Defecto;
use App\Models\Qal\Equipo;
use App\Models\Qal\Obra;
use App\Models\Qal\Operador;
use App\Models\Qal\Responsable;
use App\Models\Qal\Soldador;
use App\Models\Qal\SupervisorPintura;
use App\Models\Qal\TipoPieza;
use App\Services\Qal\RegistradorInspeccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La captura de inspección: el `captura.html` de la aplicación anterior, ahora
 * contra la base.
 *
 * Una sola pantalla para las tres transformaciones, porque así la usa el
 * inspector: la tablet se queda abierta en Formularios y lo que cambia es la
 * fase. La obra y la pieza vienen de Producción; aquí no hay padrón propio.
 */
class InspeccionController extends Controller
{
    public function __construct(private readonly RegistradorInspeccion $registrador) {}

    /**
     * Las marcas llegan sólo cuando hay obra elegida y se recargan al cambiarla:
     * una obra trae cientos, y todas las obras juntas serían miles que nadie
     * va a mirar.
     */
    public function create(Request $request): Response
    {
        $obraId = $request->integer('obra') ?: null;

        return Inertia::render('admin/calidad/formularios/index', [
            'obras' => fn () => $this->obras(),
            'obraId' => $obraId,
            'marcas' => fn () => $obraId ? $this->marcasDeLaObra($obraId) : [],
            'catalogos' => fn () => $this->catalogos(),
        ]);
    }

    public function store(InspeccionRequest $request): RedirectResponse
    {
        $inspeccion = $this->registrador->registrar(
            $request->safe()->except('fotos'),
            $request->user(),
            $request->file('fotos', []),
        );

        return back()->with('success', "Inspección {$inspeccion->folio} guardada.");
    }

    /**
     * Las obras que Calidad ya conoce y siguen activas. La llave que se manda
     * es la de la obra del portal: la inspección cuelga de ahí, no de la ficha
     * de Calidad.
     *
     * @return Collection<int, array{id: int, no: string|null, descripcion: string|null}>
     */
    private function obras(): Collection
    {
        return Obra::query()
            ->conDatosDeLaObra()
            ->where('obras.activa', true)
            ->orderBy('obras.no')
            ->get()
            ->map(fn (Obra $obra): array => [
                'id' => $obra->obra_id,
                'no' => $obra->no,
                'descripcion' => $obra->descripcion,
            ]);
    }

    /**
     * Las marcas del catálogo vigente, que son las que están en la nave.
     *
     * @return Collection<int, Concepto>
     */
    private function marcasDeLaObra(int $obraId): Collection
    {
        return Concepto::query()
            ->deCatalogoVigente()
            ->where('obra_id', $obraId)
            ->where('activo', true)
            ->get(['id', 'marca', 'lote', 'descripcion', 'cantidad', 'peso_unitario'])
            ->sortBy('marca', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Sólo lo activo: un valor desactivado sale de los desplegables, aunque
     * siga nombrado en lo ya capturado.
     *
     * @return array<string, Collection<int, \Illuminate\Database\Eloquent\Model>>
     */
    private function catalogos(): array
    {
        return [
            'equipos' => Equipo::ordenNatural(Equipo::query()->activos()->get(['id', 'nombre'])),
            'operadores' => Operador::ordenNatural(Operador::query()->activos()->get(['id', 'nombre'])),
            'responsables' => Responsable::ordenNatural(Responsable::query()->activos()->get(['id', 'nombre'])),
            'supervisoresPintura' => SupervisorPintura::ordenNatural(SupervisorPintura::query()->activos()->get(['id', 'nombre'])),
            'soldadores' => Soldador::ordenNatural(Soldador::query()->activos()->get(['id', 'nombre', 'clave'])),
            'tiposPieza' => TipoPieza::ordenNatural(TipoPieza::query()->activos()->get(['id', 'prefijo', 'descripcion'])),
            'defectosSoldadura' => Defecto::ordenNatural(Defecto::query()->activos()->deAmbito(AmbitoDefecto::Soldadura)->get(['id', 'nombre'])),
            'defectosPintura' => Defecto::ordenNatural(Defecto::query()->activos()->deAmbito(AmbitoDefecto::Pintura)->get(['id', 'nombre'])),
        ];
    }
}
