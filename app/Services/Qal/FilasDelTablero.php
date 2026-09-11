<?php

namespace App\Services\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\TipoDatoPunto;
use App\Enums\Qal\VeredictoLote;
use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionDefecto;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\Inspector;
use App\Models\Qal\Junta;
use App\Models\Qal\Muestreo;
use App\Models\Qal\Pintura;
use App\Models\Qal\Soldador;
use App\Models\Qal\TipoPieza;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las inspecciones que mira el tablero, acotadas por su barra de filtros y
 * reducidas a filas planas: una por inspección, con lo que las cuentas
 * necesitan ya resuelto —nombres, exposición, defectos—.
 *
 * Es la tabla `registros` del tablero anterior, armada de las tablas nuevas:
 * los defectos salen de `qal_inspeccion_defectos`, los elementos del punto
 * `p2_elem`, el área y el espesor de la pintura y el lote del muestreo. Aquí
 * sólo se lee; las cuentas viven en `TableroCalidad`.
 */
class FilasDelTablero
{
    /**
     * @param  array<string, string|null>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(array $filtros): Collection
    {
        $ids = $this->consulta($filtros)->select('id');

        $defectos = InspeccionDefecto::query()
            ->whereIn('inspeccion_id', $ids)
            ->with('defecto:id,nombre,ambito')
            ->get()
            ->groupBy('inspeccion_id');

        // Sólo lo que las cuentas usan: las fallas y el número de elementos.
        $puntos = InspeccionPunto::query()
            ->whereIn('inspeccion_id', $ids)
            ->where(fn (Builder $consulta) => $consulta
                ->where('resultado', ResultadoPunto::NoOk->value)
                ->orWhereHas('punto', fn (Builder $punto) => $punto->where('clave', 'p2_elem')))
            ->with('punto:id,clave,etiqueta,tipo_dato,calculado')
            ->get()
            ->groupBy('inspeccion_id');

        $pinturas = Pintura::query()
            ->whereIn('inspeccion_id', $ids)
            ->get(['inspeccion_id', 'area_m2', 'promedio_mils', 'espesor_requerido_mils'])
            ->keyBy('inspeccion_id');
        $muestreos = Muestreo::query()->whereIn('inspeccion_id', $ids)->get()->keyBy('inspeccion_id');

        return $this->consulta($filtros)
            ->with(['obra:id,no', 'tipoPieza:id,descripcion', 'inspector.usuario:id,name', 'soldador:id,clave,nombre'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (Inspeccion $inspeccion): array => $this->fila(
                $inspeccion,
                $defectos->get($inspeccion->id, collect()),
                $puntos->get($inspeccion->id, collect()),
                $pinturas->get($inspeccion->id),
                $muestreos->get($inspeccion->id),
            ));
    }

    /**
     * Las juntas del mapeo de las inspecciones filtradas. La clave sigue a la
     * misma junta de la misma pieza entre reinspecciones.
     *
     * @param  array<string, string|null>  $filtros
     * @return Collection<int, array{clave: string, intento: int, correcta: bool}>
     */
    public function juntas(array $filtros): Collection
    {
        return Junta::query()
            ->whereIn('inspeccion_id', $this->consulta($filtros)->select('id'))
            ->with('inspeccion:id,obra_id,qr')
            ->get(['id', 'inspeccion_id', 'identificador', 'intento', 'resultado'])
            ->map(fn (Junta $junta): array => [
                'clave' => $junta->inspeccion->obra_id.'|'.$junta->inspeccion->qr.'|'.$junta->identificador,
                'intento' => (int) $junta->intento,
                'correcta' => $junta->resultado === ResultadoJunta::Correcta,
            ]);
    }

    /**
     * Cuántas veces se contestó cada punto con cada resultado en las
     * inspecciones filtradas. Las casillas en blanco no tienen fila: salen de
     * restarle esto a las inspecciones donde el punto aplicaba.
     *
     * @param  array<string, string|null>  $filtros
     * @return Collection<int, array{punto_id: int, resultado: string, n: int}>
     */
    public function respuestas(array $filtros): Collection
    {
        return InspeccionPunto::query()
            ->whereIn('inspeccion_id', $this->consulta($filtros)->select('id'))
            ->whereNotNull('resultado')
            ->toBase()
            ->select(['punto_id', 'resultado'])
            ->selectRaw('count(*) as n')
            ->groupBy('punto_id', 'resultado')
            ->get()
            ->map(fn (object $fila): array => [
                'punto_id' => (int) $fila->punto_id,
                'resultado' => (string) $fila->resultado,
                'n' => (int) $fila->n,
            ]);
    }

    /**
     * Lo que ofrece la barra de filtros: sólo lo que tiene inspecciones, y sin
     * acotar por los filtros puestos. Si no, elegir una obra dejaría la lista
     * de obras con una sola.
     *
     * @return array{obras: list<array{valor: string, texto: string}>, soldadores: list<array{valor: string, texto: string}>, inspectores: list<array{valor: string, texto: string}>, tipos: list<array{valor: string, texto: string}>, semanas: list<string>}
     */
    public function opciones(): array
    {
        $usados = fn (string $columna): Collection => Inspeccion::query()->whereNotNull($columna)->distinct()->pluck($columna);

        return [
            'obras' => ObraDelPortal::query()
                ->whereIn('id', $usados('obra_id'))
                ->get(['id', 'no'])
                ->map(fn (ObraDelPortal $obra): array => ['valor' => (string) $obra->id, 'texto' => (string) $obra->no])
                ->sortBy('texto', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
            'soldadores' => Soldador::query()
                ->whereIn('id', $usados('soldador_id'))
                ->orderBy('clave')
                ->get(['id', 'clave', 'nombre'])
                ->map(fn (Soldador $soldador): array => ['valor' => (string) $soldador->id, 'texto' => trim($soldador->clave.' · '.$soldador->nombre, ' ·')])
                ->all(),
            'inspectores' => Inspector::query()
                ->whereIn('id', $usados('inspector_id'))
                ->with('usuario:id,name')
                ->get(['id', 'usuario_id'])
                ->map(fn (Inspector $inspector): array => ['valor' => (string) $inspector->id, 'texto' => (string) ($inspector->usuario?->name ?? "Inspector {$inspector->id}")])
                ->sortBy('texto', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
            'tipos' => TipoPieza::query()
                ->whereIn('id', $usados('tipo_pieza_id'))
                ->orderBy('descripcion')
                ->get(['id', 'descripcion'])
                ->map(fn (TipoPieza $tipo): array => ['valor' => (string) $tipo->id, 'texto' => (string) $tipo->descripcion])
                ->all(),
            'semanas' => Inspeccion::query()
                ->select(['anio', 'semana'])
                ->distinct()
                ->orderByDesc('anio')
                ->orderByDesc('semana')
                ->get()
                ->map(fn (Inspeccion $inspeccion): string => sprintf('%04d-S%02d', $inspeccion->anio, $inspeccion->semana))
                ->all(),
        ];
    }

    /**
     * La pieza es la de `mismaPiezaYEtapa`: en 1ª la marca con su consecutivo,
     * de 2ª en adelante el QR. La etapa le suma la sub-etapa, porque armado y
     * soldado tienen cada uno su veredicto.
     *
     * Los defectos que se cuentan son los de la fase: soldadura en 2ª, pintura
     * en 3ª. Las fallas —de armado y de 1ª, que no usan el catálogo de
     * defectos— son los puntos que no cumplen, sin los calculados —repetirían
     * a los que los alimentan— y con los contadores por su número: cada
     * elemento faltante es una falla.
     *
     * @param  Collection<int, InspeccionDefecto>  $defectos
     * @param  Collection<int, InspeccionPunto>  $puntos
     * @return array<string, mixed>
     */
    private function fila(Inspeccion $inspeccion, Collection $defectos, Collection $puntos, ?Pintura $pintura, ?Muestreo $muestreo): array
    {
        $fase = $inspeccion->fase;
        $armado = $inspeccion->subetapa === Subetapa::ArmadoVestido;
        $obra = (string) ($inspeccion->obra?->no ?? $inspeccion->obra_id);
        $ambito = match ($fase) {
            FaseTransformacion::Segunda => AmbitoDefecto::Soldadura,
            FaseTransformacion::Tercera => AmbitoDefecto::Pintura,
            default => null,
        };
        $pieza = $fase === FaseTransformacion::Primera
            ? implode('|', [$inspeccion->obra_id, $fase->value, $inspeccion->marca, $inspeccion->lote, $inspeccion->consecutivo])
            : implode('|', [$inspeccion->obra_id, $fase->value, $inspeccion->qr]);
        $lote = $muestreo !== null && $muestreo->tamano_lote > 1 ? $muestreo->tamano_lote : 1;
        $elementos = $puntos->first(fn (InspeccionPunto $respuesta): bool => $respuesta->punto->clave === 'p2_elem')?->valor_numerico;

        return [
            'id' => $inspeccion->id,
            'obra_id' => (int) $inspeccion->obra_id,
            'obra' => $obra,
            'fase' => $fase->value,
            'subetapa' => $inspeccion->subetapa?->etiqueta(),
            'subtipo' => $inspeccion->subtipo?->etiqueta(),
            'armado' => $armado,
            'fecha' => $inspeccion->fecha->toDateString(),
            'semana' => sprintf('%04d-S%02d', $inspeccion->anio, $inspeccion->semana),
            'mes' => $inspeccion->fecha->format('Y-m'),
            'estatus' => $inspeccion->estatus->value,
            'inspeccion' => (int) $inspeccion->numero_inspeccion,
            'pieza' => $pieza,
            'etapa' => $pieza.'|'.$inspeccion->subetapa?->value,
            'qr' => $inspeccion->qr,
            'kg' => $inspeccion->kg !== null ? (float) $inspeccion->kg : null,
            'elementos' => $elementos !== null ? (float) $elementos : null,
            'area' => $pintura?->area_m2 !== null ? (float) $pintura->area_m2 : null,
            // El promedio de película seca y el mínimo que pide el proyecto.
            'espesor' => $pintura?->promedio_mils !== null ? (float) $pintura->promedio_mils : null,
            'requerido' => $pintura?->espesor_requerido_mils !== null ? (float) $pintura->espesor_requerido_mils : null,
            // Un lote con muestreo ampara varias piezas, pero el inspector sólo
            // miró la muestra: lo liberado y lo inspeccionado no son lo mismo.
            'lote' => $lote,
            'muestra' => $lote > 1 ? max(1, (int) $muestreo?->muestra) : 1,
            'loteSinDisposicion' => $lote > 1
                && $muestreo?->veredicto === VeredictoLote::Rechazado
                && trim((string) $muestreo->disposicion) === '',
            // «1.1» existe en varias obras y son módulos distintos.
            'modulo' => $inspeccion->modulo ? $inspeccion->modulo.' · '.$obra : null,
            'tipo' => $inspeccion->tipoPieza?->descripcion,
            'inspector' => $inspeccion->inspector?->usuario?->name,
            'soldador' => $inspeccion->soldador?->clave ?: $inspeccion->soldador?->nombre,
            'defectos' => $ambito === null ? [] : $defectos
                ->filter(fn (InspeccionDefecto $defecto): bool => $defecto->defecto->ambito === $ambito)
                ->groupBy(fn (InspeccionDefecto $defecto): string => $defecto->defecto->nombre)
                ->map(fn (Collection $mismos): int => (int) $mismos->sum('cantidad'))
                ->all(),
            'fallas' => ! $armado && $fase !== FaseTransformacion::Primera ? [] : $puntos
                ->filter(fn (InspeccionPunto $respuesta): bool => $respuesta->resultado === ResultadoPunto::NoOk && ! $respuesta->punto->calculado)
                ->groupBy(fn (InspeccionPunto $respuesta): string => $respuesta->punto->etiqueta)
                ->map(fn (Collection $mismas): int => (int) $mismas->sum(
                    fn (InspeccionPunto $respuesta): int => $respuesta->punto->tipo_dato === TipoDatoPunto::Contador
                        ? max(1, (int) $respuesta->valor_numerico)
                        : 1,
                ))
                ->all(),
        ];
    }

    /**
     * @param  array<string, string|null>  $filtros
     * @return Builder<Inspeccion>
     */
    private function consulta(array $filtros): Builder
    {
        $semana = $filtros['semana'] ?? null;

        return Inspeccion::query()
            ->when($filtros['fase'] ?? null, fn (Builder $consulta, mixed $fase) => $consulta->where('fase', $fase))
            ->when($filtros['obra'] ?? null, fn (Builder $consulta, mixed $obra) => $consulta->where('obra_id', (int) $obra))
            ->when($filtros['subetapa'] ?? null, fn (Builder $consulta, mixed $subetapa) => $consulta->where('subetapa', $subetapa))
            ->when($filtros['soldador'] ?? null, fn (Builder $consulta, mixed $soldador) => $consulta->where('soldador_id', (int) $soldador))
            ->when($filtros['inspector'] ?? null, fn (Builder $consulta, mixed $inspector) => $consulta->where('inspector_id', (int) $inspector))
            ->when($filtros['tipo'] ?? null, fn (Builder $consulta, mixed $tipo) => $consulta->where('tipo_pieza_id', (int) $tipo))
            ->when($filtros['subtipo1'] ?? null, fn (Builder $consulta, mixed $subtipo) => $consulta->where('subtipo', $subtipo))
            ->when($filtros['desde'] ?? null, fn (Builder $consulta, mixed $desde) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $consulta, mixed $hasta) => $consulta->whereDate('fecha', '<=', $hasta))
            ->when($semana, fn (Builder $consulta) => $consulta
                ->where('anio', (int) substr((string) $semana, 0, 4))
                ->where('semana', (int) substr((string) $semana, 6)));
    }
}
