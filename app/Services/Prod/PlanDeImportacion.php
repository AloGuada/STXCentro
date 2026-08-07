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
            'renglones' => $detalle,
            'mostrados' => count($detalle),
            'truncado' => count($detalle) < count($this->renglones),
        ];
    }
}
