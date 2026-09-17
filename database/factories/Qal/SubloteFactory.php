<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;
use App\Models\Qal\Inspector;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Sublote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Sublote>
 */
class SubloteFactory extends Factory
{
    protected $model = Sublote::class;

    /**
     * Cien unidades a nivel II, con la muestra completa y aceptada.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = now()->startOfDay();

        return [
            'lote_id' => LoteAccesorio::factory(),
            'numero_inspeccion' => 1,
            'unidades' => 100,
            'fecha' => $fecha,
            'anio' => $fecha->isoWeekYear(),
            'semana' => $fecha->isoWeek(),
            'inspector_id' => Inspector::factory(),
            'nivel' => NivelAql::Normal,
            'muestra' => 20,
            'aceptacion' => 5,
            'rechazo' => 6,
            'conformes' => 20,
            'rechazadas' => 0,
            'veredicto' => VeredictoLote::Aceptado,
            'capturado_en' => now(),
        ];
    }

    public function rechazado(?string $disposicion = null): static
    {
        return $this->state(fn (): array => [
            'conformes' => 14,
            'rechazadas' => 6,
            'veredicto' => VeredictoLote::Rechazado,
            'disposicion' => $disposicion,
        ]);
    }

    /** La reinspección siguiente de un sublote. */
    public function reinspeccionDe(Sublote $anterior): static
    {
        return $this->state(fn (): array => [
            'lote_id' => $anterior->lote_id,
            'sublote_origen_id' => $anterior->grupoId(),
            'numero_inspeccion' => $anterior->numero_inspeccion + 1,
            'unidades' => $anterior->unidades,
        ]);
    }
}
