<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Obra;

/**
 * Un formato oficial F-STX-* de Calidad: su encabezado, de qué etapa lee y cómo
 * arma sus filas.
 *
 * Cada formato dice si va «para el dosier» —se entrega al cliente— o es de
 * «uso interno». No es decorativo: en los del dosier una pieza liberada sale
 * sin defectos y la hoja arranca en su estado final con sólo las liberadas; en
 * los internos se enseña lo capturado tal cual y arranca en el histórico.
 */
abstract class Formato
{
    public const DOSIER = 'dosier';

    public const INTERNO = 'interno';

    public function __construct(
        protected FilasDeReporte $filas,
        protected VistaDeReporte $vistas,
    ) {}

    /** El segmento de la URL. */
    abstract public function clave(): string;

    abstract public function codigo(): string;

    abstract public function revision(): string;

    abstract public function titulo(): string;

    abstract public function subtitulo(): string;

    abstract public function destino(): string;

    abstract public function fase(): FaseTransformacion;

    /** El nombre de la vista blade dentro de `pdf.qal.formatos`. */
    abstract public function vista(): string;

    /**
     * Lo que la vista necesita: `generales`, `filas`, `totales`, `vacio`,
     * `nota`, `leyenda` y `creador_id` (el usuario que elaboró, para firmar).
     *
     * @return array<string, mixed>
     */
    abstract public function datos(FiltrosDeReporte $filtros): array;

    public function subetapa(): ?Subetapa
    {
        return null;
    }

    /** El mapeo es de una pieza, no de un periodo. */
    public function usaPeriodo(): bool
    {
        return true;
    }

    public function paraElDosier(): bool
    {
        return $this->destino() === self::DOSIER;
    }

    public function vistaPorDefecto(): string
    {
        return $this->paraElDosier() ? 'final' : 'historico';
    }

    public function estatusPorDefecto(): string
    {
        return $this->paraElDosier() ? 'liberadas' : 'todas';
    }

    /**
     * Lo que la pantalla de Reportes necesita para ofrecerlo.
     *
     * @return array{clave: string, codigo: string, titulo: string, destino: string, fase: string, subetapa: string|null, usaPeriodo: bool, vistaPorDefecto: string, estatusPorDefecto: string}
     */
    public function ficha(): array
    {
        return [
            'clave' => $this->clave(),
            'codigo' => $this->codigo(),
            'titulo' => $this->titulo(),
            'destino' => $this->destino(),
            'fase' => $this->fase()->value,
            'subetapa' => $this->subetapa()?->value,
            'usaPeriodo' => $this->usaPeriodo(),
            'vistaPorDefecto' => $this->vistaPorDefecto(),
            'estatusPorDefecto' => $this->estatusPorDefecto(),
        ];
    }

    /**
     * La regla del dosier: en su vista final, una pieza liberada no tiene
     * defectos. En el histórico se enseña lo que se capturó en cada intento,
     * con sus D: si no, se pierde la trazabilidad del retrabajo.
     *
     * @param  array<string, mixed>  $fila
     */
    protected function limpia(array $fila, FiltrosDeReporte $filtros): bool
    {
        return $this->paraElDosier() && $filtros->vista === 'final' && $fila['estatus'] === 'liberado';
    }

    /**
     * En el dosier, N/A cuando la pieza está liberada o no hay nada escrito.
     *
     * @param  array<string, mixed>  $fila
     */
    protected function observaciones(array $fila, FiltrosDeReporte $filtros): string
    {
        if ($this->limpia($fila, $filtros)) {
            return 'N/A';
        }

        return $fila['observaciones'] !== '' ? $fila['observaciones'] : ($this->paraElDosier() ? 'N/A' : '');
    }

    protected function obra(int $obraId): string
    {
        $obra = Obra::query()->find($obraId, ['id', 'no', 'descripcion']);

        return $obra ? trim("{$obra->no} — {$obra->descripcion}", ' —') : '';
    }

    /**
     * Distinguir «no hay nada capturado» de «no hay nada en ESTE filtro» ahorra
     * media hora de buscar un fallo que no existe.
     */
    protected function vacio(int $total, string $que, FiltrosDeReporte $filtros): string
    {
        if ($total === 0) {
            return "Todavía no hay {$que} en esta obra.";
        }

        if ($filtros->periodo === 'todo') {
            return "Hay {$total} {$que} en la obra, pero ninguna con ese inspector y estatus.";
        }

        return "Hay {$total} {$que} en la obra, pero ninguna en {$filtros->periodoEnFrase()} con ese inspector y estatus. "
            .'Prueba otro periodo o «Todo el proyecto».';
    }

    /**
     * El usuario que elaboró la hoja: el inspector de la primera fila.
     *
     * @param  iterable<array<string, mixed>>  $filas
     */
    protected function creador(iterable $filas): ?string
    {
        foreach ($filas as $fila) {
            if ($fila['inspector_usuario_id'] ?? null) {
                return $fila['inspector_usuario_id'];
            }
        }

        return null;
    }
}
