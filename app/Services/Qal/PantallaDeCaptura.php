<?php

namespace App\Services\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Models\Concepto;
use App\Models\Qal\Defecto;
use App\Models\Qal\Equipo;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Obra;
use App\Models\Qal\Operador;
use App\Models\Qal\Responsable;
use App\Models\Qal\Soldador;
use App\Models\Qal\SupervisorPintura;
use App\Models\Qal\TipoPieza;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla de captura con todo lo que necesita: obras, marcas y lotes de la
 * obra elegida, catálogos, y lo que se precarga cuando se abre para editar o
 * reinspeccionar.
 *
 * Es una sola pantalla para piezas y accesorios, y se abre desde varios
 * lugares (Formularios, Registros, Accesorios); por eso se arma aquí y no en
 * cada controlador.
 */
class PantallaDeCaptura
{
    public function __construct(private readonly AvanceDeCaptura $avance) {}

    /**
     * Marcas y lotes llegan sólo con obra elegida y se recargan al cambiarla:
     * una obra trae cientos de marcas, y todas las obras juntas serían miles
     * que nadie va a mirar.
     *
     * @param  array<string, mixed>|null  $precarga  ver PrecargaDeFormulario
     */
    public function mostrar(?int $obraId, ?array $precarga = null, ?int $conceptoId = null): Response
    {
        return Inertia::render('admin/calidad/formularios/index', [
            'obras' => fn () => Obra::opcionesDeSelector(),
            'obraId' => $obraId,
            'marcas' => fn () => $obraId ? $this->marcasDeLaObra($obraId) : [],
            'lotes' => fn () => $obraId ? $this->lotesDeLaObra($obraId) : [],
            // La pestaña Registros: las marcas con su avance, y las piezas de
            // la marca que se abrió.
            'avance' => fn () => $obraId ? $this->avance->marcasDeLaObra($obraId) : [],
            'piezasDeMarca' => fn () => $conceptoId
                ? ['conceptoId' => $conceptoId, 'piezas' => $this->avance->piezasDeLaMarca($conceptoId)]
                : null,
            'catalogos' => fn () => $this->catalogos(),
            'precarga' => $precarga,
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
     * Los lotes de accesorios ya declarados: al teclear una marca que existe,
     * la captura hereda sus datos y dice cuántas unidades van.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lotesDeLaObra(int $obraId): Collection
    {
        return LoteAccesorio::query()
            ->with('sublotes')
            ->where('obra_id', $obraId)
            ->orderBy('marca')
            ->get()
            ->map(fn (LoteAccesorio $lote): array => [
                'id' => $lote->id,
                'marca' => $lote->marca,
                'descripcion' => $lote->descripcion,
                'total_unidades' => $lote->total_unidades,
                'kg_unitario' => $lote->kg_unitario,
                'elementos_unitarios' => $lote->elementos_unitarios,
                'avance' => $lote->avance(),
            ]);
    }

    /**
     * Sólo lo activo: un valor desactivado sale de los desplegables, aunque
     * siga nombrado en lo ya capturado.
     *
     * @return array<string, Collection<int, \Illuminate\Database\Eloquent\Model>>
     */
    private function catalogos(): array
    {
        $defectos = fn (AmbitoDefecto $ambito) => Defecto::ordenNatural(
            Defecto::query()->activos()->deAmbito($ambito)->get(['id', 'nombre']),
        );

        return [
            'equipos' => Equipo::ordenNatural(Equipo::query()->activos()->get(['id', 'nombre'])),
            'operadores' => Operador::ordenNatural(Operador::query()->activos()->get(['id', 'nombre'])),
            'responsables' => Responsable::ordenNatural(Responsable::query()->activos()->get(['id', 'nombre'])),
            'supervisoresPintura' => SupervisorPintura::ordenNatural(SupervisorPintura::query()->activos()->get(['id', 'nombre'])),
            'soldadores' => Soldador::ordenNatural(Soldador::query()->activos()->get(['id', 'nombre', 'clave'])),
            'tiposPieza' => TipoPieza::ordenNatural(TipoPieza::query()->activos()->get(['id', 'prefijo', 'descripcion'])),
            'defectosSoldadura' => $defectos(AmbitoDefecto::Soldadura),
            'defectosPintura' => $defectos(AmbitoDefecto::Pintura),
            'defectosAccDimensional' => $defectos(AmbitoDefecto::AccesorioDimensional),
            'defectosAccBarrenos' => $defectos(AmbitoDefecto::AccesorioBarrenos),
            'defectosAccLimpieza' => $defectos(AmbitoDefecto::AccesorioLimpieza),
        ];
    }
}
