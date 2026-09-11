<?php

namespace App\Services\Qal\Formatos;

use Carbon\CarbonImmutable;

/**
 * Lo que acota un formato: la obra —siempre una, el formato es de una obra—,
 * el periodo, el inspector, el estatus y cómo se lee la pieza (su estado
 * final, su hoja de revisión o su historia intento por intento).
 *
 * El mapeo no es de un periodo sino de una pieza, y la trae aparte.
 */
final readonly class FiltrosDeReporte
{
    public const PERIODOS = ['dia', 'semana', 'todo'];

    public const ESTATUS = ['liberadas', 'todas', 'rechazadas', 'pendientes'];

    public const VISTAS = ['final', 'revision', 'historico'];

    public function __construct(
        public int $obraId,
        public string $periodo = 'todo',
        public ?string $fecha = null,
        public ?string $semana = null,
        public ?int $inspectorId = null,
        public string $estatus = 'todas',
        public string $vista = 'historico',
        public ?int $piezaId = null,
    ) {}

    /** Los mismos filtros leídos de otra forma: el control diario siempre va en su estado final. */
    public function conVista(string $vista): self
    {
        return new self($this->obraId, $this->periodo, $this->fecha, $this->semana, $this->inspectorId, $this->estatus, $vista, $this->piezaId);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function anioYSemana(): ?array
    {
        if ($this->periodo !== 'semana' || ! preg_match('/^(\d{4})-S(\d{2})$/', (string) $this->semana, $partes)) {
            return null;
        }

        return [(int) $partes[1], (int) $partes[2]];
    }

    public function periodoTexto(): string
    {
        return match ($this->periodo) {
            'dia' => $this->fecha ? CarbonImmutable::parse($this->fecha)->format('d/m/Y') : '—',
            'semana' => 'Semana '.$this->semana,
            default => 'Proyecto completo',
        };
    }

    /** Cómo se nombra el periodo dentro de una frase: «en la semana…», «el día…». */
    public function periodoEnFrase(): string
    {
        return match ($this->periodo) {
            'dia' => 'el día '.$this->periodoTexto(),
            'semana' => 'la semana '.$this->semana,
            default => 'el proyecto',
        };
    }
}
