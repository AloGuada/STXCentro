<?php

namespace App\Services\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;
use App\Models\Concepto;
use App\Models\Qal\Inspector;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Sublote;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una entrega de accesorios: el lote y la inspección del sublote, en la
 * misma transacción.
 *
 * En la aplicación anterior eran dos escrituras sueltas y, si fallaba la
 * segunda, quedaba un lote sin sublote que nadie veía. Aquí el lote se crea o
 * se actualiza con la marca y la obra, y el sublote cuelga de él, o no se
 * guarda nada.
 *
 * Una reinspección es un sublote nuevo del mismo grupo con el número
 * siguiente: la anterior se conserva, porque es la que dice que hubo un
 * rechazo. Editar, en cambio, sobrescribe la inspección que se abrió.
 */
class RegistradorSublote
{
    public function __construct(private readonly CalculadorAql $aql) {}

    /**
     * @param  array<string, mixed>  $datos  lo validado por SubloteRequest
     */
    public function registrar(array $datos, Usuario $capturista, ?Sublote $sublote = null): Sublote
    {
        return DB::transaction(function () use ($datos, $capturista, $sublote): Sublote {
            $origen = $sublote === null && ! empty($datos['sublote_origen_id'])
                ? Sublote::query()->findOrFail($datos['sublote_origen_id'])
                : null;

            $lote = $this->lote($datos, $capturista, $sublote?->lote ?? $origen?->lote);

            $unidades = (int) $datos['unidades'];
            $nivel = NivelAql::from($datos['nivel']);
            $plan = $this->aql->plan($unidades, $nivel, tope: $unidades);
            $rechazadas = array_values($datos['rechazadas'] ?? []);
            $veredicto = $this->aql->veredicto($plan, (int) $datos['conformes'], count($rechazadas));
            $fecha = Carbon::parse($datos['fecha']);

            $sublote ??= new Sublote([
                'sublote_origen_id' => $origen?->grupoId(),
                'numero_inspeccion' => $origen ? $this->siguienteNumero($origen) : 1,
                'inspector_id' => Inspector::query()->firstOrCreate(
                    ['usuario_id' => $capturista->getKey()],
                    ['fase' => FaseTransformacion::Segunda],
                )->id,
                'capturado_en' => now(),
                'capturista_id' => $capturista->getKey(),
            ]);

            $sublote->fill([
                'lote_id' => $lote->id,
                'unidades' => $unidades,
                'fecha' => $fecha,
                'anio' => $fecha->isoWeekYear(),
                'semana' => $fecha->isoWeek(),
                'linea' => $datos['linea'] ?? null,
                'modulo' => $datos['modulo'] ?? null,
                'responsable_id' => $datos['responsable_id'] ?? null,
                'soldador_id' => $datos['soldador_id'] ?? null,
                'nivel' => $nivel,
                ...$plan,
                'conformes' => (int) $datos['conformes'],
                'rechazadas' => count($rechazadas),
                'veredicto' => $veredicto,
                // En blanco es «pendiente de decidir»: así lo señala Registros.
                'disposicion' => $veredicto === VeredictoLote::Rechazado ? ($datos['disposicion'] ?? null) : null,
                'observaciones' => $datos['observaciones'] ?? null,
            ])->save();

            $sublote->defectos()->delete();
            $sublote->defectos()->createMany($this->filasDeDefectos($rechazadas));

            return $sublote;
        });
    }

    /**
     * El lote de la marca en la obra. Los datos de la marca (total, peso,
     * elementos) se actualizan con cada entrega: son del lote entero, y la
     * última captura es la que tiene el plano a la vista.
     *
     * @param  array<string, mixed>  $datos
     */
    private function lote(array $datos, Usuario $capturista, ?LoteAccesorio $existente): LoteAccesorio
    {
        $marca = mb_strtoupper(trim((string) $datos['marca']));

        $lote = $existente ?? LoteAccesorio::query()->firstOrNew([
            'obra_id' => $datos['obra_id'],
            'marca' => $marca,
        ]);

        $lote->fill([
            'descripcion' => $datos['descripcion'] ?? null,
            'total_unidades' => $datos['total_unidades'],
            'kg_unitario' => $datos['kg_unitario'] ?? null,
            'elementos_unitarios' => $datos['elementos_unitarios'] ?? null,
        ]);

        if (! $lote->exists) {
            $lote->capturista_id = $capturista->getKey();
            $lote->concepto_id = $this->conceptoDeLaMarca((int) $datos['obra_id'], $marca);
        }

        $lote->save();

        return $lote;
    }

    /**
     * La marca de Producción, si en el catálogo vigente hay exactamente una con
     * ese nombre. Con dos (la misma marca en dos lotes de fabricación) no se
     * adivina: el lote se queda con el texto.
     */
    private function conceptoDeLaMarca(int $obraId, string $marca): ?int
    {
        $ids = Concepto::query()
            ->deCatalogoVigente()
            ->where('obra_id', $obraId)
            ->where('marca', $marca)
            ->pluck('id');

        return $ids->count() === 1 ? $ids->first() : null;
    }

    private function siguienteNumero(Sublote $origen): int
    {
        $grupo = $origen->grupoId();

        return (int) Sublote::query()
            ->where(fn ($consulta) => $consulta->where('id', $grupo)->orWhere('sublote_origen_id', $grupo))
            ->max('numero_inspeccion') + 1;
    }

    /**
     * Una fila por unidad rechazada y defecto. La unidad es su número dentro de
     * las rechazadas: la primera que se marcó con defecto es la 1.
     *
     * @param  list<array{defectos?: list<int|string>}>  $rechazadas
     * @return list<array{unidad: int, defecto_id: int}>
     */
    private function filasDeDefectos(array $rechazadas): array
    {
        $filas = [];

        foreach ($rechazadas as $indice => $unidad) {
            foreach (array_unique(array_map('intval', $unidad['defectos'] ?? [])) as $defectoId) {
                $filas[] = ['unidad' => $indice + 1, 'defecto_id' => $defectoId];
            }
        }

        return $filas;
    }
}
