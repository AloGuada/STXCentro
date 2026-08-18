<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\PedidoEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'departamento_id' => Departamento::factory(),
            // Por default es consumo interno de planta, que es el caso que se
            // surte con una salida.
            'obra_id' => null,
            'solicitante_id' => Usuario::factory(),
            'fecha' => now()->toDateString(),
            'fecha_requerida' => now()->addDays(2)->toDateString(),
            'estatus' => PedidoEstatus::Aprobado,
        ];
    }

    public function de(Almacen $almacen): static
    {
        return $this->state(fn (): array => ['almacen_id' => $almacen->id]);
    }

    /** Un pedido de obra: se surte con transferencia, no con salida. */
    public function paraObra(?Obra $obra = null): static
    {
        return $this->state(fn (): array => [
            'obra_id' => $obra?->id ?? Obra::factory(),
        ]);
    }

    public function surtido(): static
    {
        return $this->state(fn (): array => ['estatus' => PedidoEstatus::Surtido]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (): array => ['estatus' => PedidoEstatus::Cancelado]);
    }
}
