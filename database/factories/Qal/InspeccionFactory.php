<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Inspeccion>
 */
class InspeccionFactory extends Factory
{
    protected $model = Inspeccion::class;

    /**
     * Una 1ª de perfil liberada, sin marca de Producción detrás.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = now()->startOfDay();

        return [
            'obra_id' => ObraDelPortal::factory(),
            'marca' => fake()->regexify('[A-Z]{2}-[A-Z]{2}[0-9]-[0-9]{2}'),
            'fase' => FaseTransformacion::Primera,
            'subtipo' => SubtipoPrimera::Perfil,
            'fecha' => $fecha,
            'anio' => $fecha->isoWeekYear(),
            'semana' => $fecha->isoWeek(),
            'inspector_id' => Inspector::factory(),
            'numero_inspeccion' => 1,
            'consecutivo' => 1,
            'kg' => fake()->randomFloat(3, 5, 900),
            'estatus' => EstatusInspeccion::Liberado,
            'capturado_en' => now(),
        ];
    }

    /** De una marca de Producción, en 1ª. */
    public function deMarca(Concepto $concepto, int $consecutivo = 1): static
    {
        return $this->state(fn (): array => [
            'obra_id' => $concepto->obra_id,
            'catalogo_id' => $concepto->catalogo_id,
            'concepto_id' => $concepto->id,
            'marca' => $concepto->marca,
            'lote' => $concepto->lote,
            'consecutivo' => $consecutivo,
        ]);
    }

    /** De una pieza física, en 2ª (con su sub-etapa) o en pintura. */
    public function dePieza(Pieza $pieza, FaseTransformacion $fase, ?Subetapa $subetapa = null): static
    {
        return $this->state(fn (): array => [
            'obra_id' => $pieza->catalogo->obra_id,
            'catalogo_id' => $pieza->catalogo_id,
            'concepto_id' => $pieza->concepto_id,
            'prod_pieza_id' => $pieza->id,
            'marca' => $pieza->marca->marca,
            'lote' => $pieza->marca->lote,
            'qr' => $pieza->qr,
            'fase' => $fase,
            'subetapa' => $subetapa,
            'subtipo' => null,
            'consecutivo' => null,
        ]);
    }

    public function rechazada(): static
    {
        return $this->state(fn (): array => ['estatus' => EstatusInspeccion::Rechazado]);
    }
}
