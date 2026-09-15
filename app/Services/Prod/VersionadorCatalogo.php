<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use Illuminate\Support\Facades\DB;

/**
 * Crea versiones nuevas del catálogo de piezas de una obra y compara dos
 * versiones entre sí.
 *
 * La versión anterior queda congelada: la producción capturada y las
 * liquidaciones cerradas siguen apuntando a sus piezas originales, así que
 * ningún número histórico se mueve al versionar.
 */
class VersionadorCatalogo
{
    public function __construct(private readonly AvanceDePiezas $avance) {}

    /**
     * Copia el catálogo vigente de la obra a una versión nueva (que pasa a ser
     * la vigente) junto con sus asignaciones de grupo de precio.
     */
    public function nuevaVersion(Catalogo $catalogo, ?string $notas = null): Catalogo
    {
        return DB::transaction(function () use ($catalogo, $notas): Catalogo {
            $siguiente = (int) Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->max('version') + 1;

            Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->update(['vigente' => false]);

            $nueva = Catalogo::create([
                'obra_id' => $catalogo->obra_id,
                'catalogo_origen_id' => $catalogo->id,
                'nombre' => $catalogo->nombre,
                'version' => $siguiente,
                'vigente' => true,
                'notas' => $notas,
            ]);

            $this->copiarPiezas($catalogo, $nueva);

            return $nueva;
        });
    }

    /**
     * Diferencias entre dos versiones, emparejadas por modelo (marca + lote).
     * Emparejar sólo por marca juntaría piezas distintas cuando el catálogo
     * repite la marca en varios lotes de la obra.
     *
     * Cada renglón dice además cuántas piezas lleva pagadas el modelo en la
     * obra (todas las versiones) contra la cantidad de la versión `contra`:
     * `excedente` es lo pagado que la versión nueva ya no reconoce, y
     * `por_pagar` lo que abre. Es la pregunta de fondo al versionar: qué se
     * puede seguir pagando y qué ya se pagó de más.
     *
     * @return array{
     *     agregadas: list<array{marca: string, lote: ?string, descripcion: string, cantidad: int, pagadas: float, excedente: float, por_pagar: float}>,
     *     eliminadas: list<array{marca: string, lote: ?string, descripcion: string, cantidad: int, pagadas: float, excedente: float, por_pagar: float}>,
     *     modificadas: list<array{marca: string, lote: ?string, descripcion: string, cantidad: int, pagadas: float, excedente: float, por_pagar: float, cambios: list<array{campo: string, antes: mixed, despues: mixed}>}>,
     *     sin_cambios: int,
     *     pagado: array{modelos_con_exceso: int, piezas_de_mas: float, piezas_por_pagar: float}
     * }
     */
    public function comparar(Catalogo $base, Catalogo $contra): array
    {
        $this->pagadas = $this->avance->mapaDeObra((int) $contra->obra_id)->pagadasPorModelo();
        $piezasBase = $base->conceptos()->get()->keyBy(fn (Concepto $pieza) => $pieza->claveModelo());
        $piezasContra = $contra->conceptos()->get()->keyBy(fn (Concepto $pieza) => $pieza->claveModelo());

        $agregadas = [];
        $eliminadas = [];
        $modificadas = [];
        $sinCambios = 0;

        foreach ($piezasContra as $clave => $pieza) {
            if (! $piezasBase->has($clave)) {
                $agregadas[] = $this->resumenDePieza($pieza);

                continue;
            }

            $cambios = $this->cambiosEntrePiezas($piezasBase->get($clave), $pieza);

            if ($cambios === []) {
                $sinCambios++;

                continue;
            }

            $modificadas[] = [...$this->resumenDePieza($pieza), 'cambios' => $cambios];
        }

        foreach ($piezasBase as $clave => $pieza) {
            if (! $piezasContra->has($clave)) {
                $eliminadas[] = $this->resumenDePieza($pieza, eliminada: true);
            }
        }

        $conExceso = array_filter([...$agregadas, ...$eliminadas, ...$modificadas], fn (array $r): bool => $r['excedente'] > 0);

        return [
            'agregadas' => $agregadas,
            'eliminadas' => $eliminadas,
            'modificadas' => $modificadas,
            'sin_cambios' => $sinCambios,
            'pagado' => [
                'modelos_con_exceso' => count($conExceso),
                'piezas_de_mas' => round(array_sum(array_column($conExceso, 'excedente')), 4),
                'piezas_por_pagar' => round(array_sum(array_column([...$agregadas, ...$modificadas], 'por_pagar')), 4),
            ],
        ];
    }

    /**
     * Lo pagado por modelo en la obra, cargado al arrancar `comparar()`.
     *
     * @var array<string, float>
     */
    private array $pagadas = [];

    /**
     * Duplica las piezas y arrastra sus grupos de precio, para no tener que
     * reasignar precios a mano en cada versión.
     */
    private function copiarPiezas(Catalogo $origen, Catalogo $destino): void
    {
        $preciosPorConcepto = GrupoPrecioConcepto::query()
            ->whereIn('concepto_id', $origen->conceptos()->select('id'))
            ->get()
            ->groupBy('concepto_id');

        foreach ($origen->conceptos()->with('piezas')->get() as $marca) {
            $copia = Concepto::create([
                'obra_id' => $destino->obra_id,
                'catalogo_id' => $destino->id,
                // Linaje: sostiene el conteo de lo pagado aunque la marca
                // cambie de nombre en esta version.
                'concepto_origen_id' => $marca->id,
                'marca' => $marca->marca,
                'lote' => $marca->lote,
                'descripcion' => $marca->descripcion,
                'cantidad' => $marca->cantidad,
                'peso_unitario' => $marca->peso_unitario,
                'longitud' => $marca->longitud,
                'categoria_id' => $marca->categoria_id,
                'version' => $destino->version,
                'activo' => $marca->activo,
            ]);

            // Las piezas se copian con su QR y su propio linaje: el acumulado se
            // cuenta por pieza, asi que sin esto la version nueva arrancaria en
            // cero y se podria volver a pagar lo ya fabricado. Las apagadas (su
            // QR ya cambio de orden) no viajan: lo pagado bajo ellas lo
            // conserva el snapshot de la liquidacion, por modelo.
            foreach ($marca->piezas->where('activo', true) as $pieza) {
                Pieza::create([
                    'catalogo_id' => $destino->id,
                    'concepto_id' => $copia->id,
                    'qr' => $pieza->qr,
                    'qs' => $pieza->qs,
                    'pieza_origen_id' => $pieza->id,
                    'activo' => $pieza->activo,
                ]);
            }

            foreach ($preciosPorConcepto->get($marca->id, collect()) as $asignacion) {
                GrupoPrecioConcepto::create([
                    'grupo_precio_id' => $asignacion->grupo_precio_id,
                    'concepto_id' => $copia->id,
                ]);
            }
        }
    }

    /**
     * El modelo con lo que lleva pagado contra su cantidad en esta versión.
     * Para una eliminada la cantidad es 0: todo lo pagado queda en exceso.
     *
     * @return array{marca: string, lote: ?string, descripcion: string, cantidad: int, pagadas: float, excedente: float, por_pagar: float}
     */
    private function resumenDePieza(Concepto $pieza, bool $eliminada = false): array
    {
        $cantidad = $eliminada ? 0 : (int) $pieza->cantidad;
        $pagadas = $this->pagadas[$pieza->claveModelo()] ?? 0.0;

        return [
            'marca' => $pieza->marca,
            'lote' => $pieza->lote,
            'descripcion' => $pieza->descripcion,
            'cantidad' => $cantidad,
            'pagadas' => $pagadas,
            'excedente' => round(max(0, $pagadas - $cantidad), 4),
            'por_pagar' => round(max(0, $cantidad - $pagadas), 4),
        ];
    }

    /**
     * @return list<array{campo: string, antes: mixed, despues: mixed}>
     */
    private function cambiosEntrePiezas(Concepto $antes, Concepto $despues): array
    {
        $campos = [
            'descripcion' => 'Descripción',
            'cantidad' => 'Cantidad',
            'peso_unitario' => 'Peso unitario',
            'longitud' => 'Longitud',
            'categoria_id' => 'Categoría',
            'activo' => 'Activo',
        ];

        $cambios = [];

        foreach ($campos as $campo => $etiqueta) {
            if ((string) $antes->{$campo} === (string) $despues->{$campo}) {
                continue;
            }

            $cambios[] = [
                'campo' => $etiqueta,
                'antes' => $antes->{$campo},
                'despues' => $despues->{$campo},
            ];
        }

        return $cambios;
    }
}
