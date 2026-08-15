<?php

namespace App\Services\Prod;

/**
 * Lo que va a pasar si se aplica un archivo de producción, resuelto contra el
 * catálogo pero sin haber escrito nada.
 *
 * Es la misma foto que se le enseña al usuario en el modal y la que recorre el
 * import al confirmar: si fueran dos cálculos distintos, la revisión mentiría.
 *
 * @phpstan-type Renglon array{
 *     referencia: string,
 *     linea: int|null,
 *     estado: 'aplicable'|'omitida'|'error',
 *     codigo: string,
 *     motivo: string|null,
 *     pieza_id: int|null,
 *     qr: string|null,
 *     qs: string|null,
 *     marca: string|null,
 *     proceso: string|null,
 *     proceso_id: int|null,
 *     grupo: string|null,
 *     grupo_trabajo_id: int|null,
 *     porcentaje: float|null,
 *     por_qs: bool,
 *     asignado_por: 'qr'|'qs'|'marca',
 *     candidatas: int|null,
 * }
 */
readonly class PlanDeImportacion
{
    /**
     * Cuántos renglones viajan al modal. Los errores van todos —cada uno pide
     * atención— y del resto se manda una muestra: los conteos del resumen
     * siempre son del archivo completo, y aplicar recalcula sobre el original.
     */
    public const LIMITE_DETALLE = 1000;

    /**
     * @param  list<Renglon>  $renglones
     * @param  array<string, int|string>  $resumen
     * @param  list<array{evento: string, muestra: string, renglones: int}>  $ignorados
     */
    public function __construct(
        private array $renglones,
        private array $resumen,
        private array $ignorados,
    ) {}

    /**
     * Los renglones que sí se van a escribir.
     *
     * @return list<Renglon>
     */
    public function aplicables(): array
    {
        return array_values(array_filter(
            $this->renglones,
            fn (array $renglon): bool => $renglon['estado'] === 'aplicable',
        ));
    }

    /**
     * @return array<string, int|string>
     */
    public function resumen(): array
    {
        return $this->resumen;
    }

    /** ¿Hay algo que escribir? */
    public function vacio(): bool
    {
        return ($this->resumen['aplicables'] ?? 0) === 0;
    }

    /**
     * Los motivos de los renglones que no entran, sin repetirse, para poder
     * resumirlos en una línea cuando no hay modal de por medio.
     *
     * @param  'error'|'omitida'|null  $estado  Sólo los de ese estado; null trae los dos.
     * @return list<string>
     */
    public function motivos(?string $estado = null): array
    {
        $motivos = [];

        foreach ($this->renglones as $renglon) {
            $cuenta = $estado === null ? $renglon['estado'] !== 'aplicable' : $renglon['estado'] === $estado;

            if ($cuenta && $renglon['motivo'] !== null) {
                $motivos[$renglon['motivo']] = true;
            }
        }

        return array_keys($motivos);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $errores = [];
        $resto = [];

        foreach ($this->renglones as $renglon) {
            if ($renglon['estado'] === 'error') {
                $errores[] = $renglon;

                continue;
            }

            $resto[] = $renglon;
        }

        $muestra = array_slice($resto, 0, max(0, self::LIMITE_DETALLE - count($errores)));
        $detalle = [...$errores, ...$muestra];

        return [
            'resumen' => $this->resumen,
            'ignorados' => $this->ignorados,
            'por_grupo' => $this->porGrupo(),
            'renglones' => $detalle,
            'mostrados' => count($detalle),
            'truncado' => count($detalle) < count($this->renglones),
        ];
    }

    /**
     * Qué le toca a cada cuadrilla, sobre el archivo completo.
     *
     * Se calcula aquí y no en el front porque el detalle que viaja va truncado:
     * sumar los renglones que se alcanzan a mostrar daría totales cortos justo
     * en los archivos grandes, que son los que se revisan a ojo.
     *
     * Los movimientos que no llegaron a resolver grupo —una ubicación que no
     * está en el catálogo, un grupo mal escrito— caen en un renglón sin nombre:
     * son los que hay que atender antes de importar.
     *
     * @return list<array{grupo: string|null, grupo_trabajo_id: int|null, movimientos: int, piezas: float, no_entran: int}>
     */
    private function porGrupo(): array
    {
        $grupos = [];

        foreach ($this->renglones as $renglon) {
            $clave = $renglon['grupo_trabajo_id'] ?? 'sin_grupo';

            $grupos[$clave] ??= [
                'grupo' => $renglon['grupo'],
                'grupo_trabajo_id' => $renglon['grupo_trabajo_id'],
                'movimientos' => 0,
                'piezas' => 0.0,
                'no_entran' => 0,
            ];

            if ($renglon['estado'] !== 'aplicable') {
                $grupos[$clave]['no_entran']++;

                continue;
            }

            $grupos[$clave]['movimientos']++;
            $grupos[$clave]['piezas'] += ($renglon['porcentaje'] ?? 0) / 100;
        }

        foreach ($grupos as $clave => $grupo) {
            $grupos[$clave]['piezas'] = round($grupo['piezas'], 2);
        }

        // Primero quien más recibe; los que no resolvieron grupo, hasta abajo:
        // no son una cuadrilla, son trabajo pendiente sobre el archivo.
        usort($grupos, function (array $a, array $b): int {
            if (($a['grupo'] === null) !== ($b['grupo'] === null)) {
                return $a['grupo'] === null ? 1 : -1;
            }

            return $b['movimientos'] <=> $a['movimientos']
                ?: strcasecmp((string) $a['grupo'], (string) $b['grupo']);
        });

        return array_values($grupos);
    }
}
