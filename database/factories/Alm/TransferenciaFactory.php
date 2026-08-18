<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Transferencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para armar el escenario de una prueba. En producción la transferencia
 * nace por `RegistradorTransferencia`, que además descarga el origen.
 *
 * @extends Factory<Transferencia>
 */
class TransferenciaFactory extends Factory
{
    protected $model = Transferencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_origen_id' => Almacen::factory(),
            'almacen_destino_id' => Almacen::factory(),
            'estatus' => TransferenciaEstatus::EnTransito,
            'fecha_envio' => now()->toDateString(),
            'enviado_por' => Usuario::factory(),
        ];
    }

    public function entre(Almacen $origen, Almacen $destino): static
    {
        return $this->state(fn (): array => [
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
        ]);
    }

    public function recibida(): static
    {
        return $this->state(fn (): array => [
            'estatus' => TransferenciaEstatus::Recibida,
            'fecha_recepcion' => now()->toDateString(),
            'recibido_por' => Usuario::factory(),
        ]);
    }
}
