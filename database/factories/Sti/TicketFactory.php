<?php

namespace Database\Factories\Sti;

use App\Models\Departamento;
use App\Models\Sti\Equipo;
use App\Models\Sti\Tecnico;
use App\Models\Sti\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_solicitante' => fake()->name(),
            'comentario' => fake()->paragraph(),
            'tecnico_id' => null,
            'equipo_id' => null,
            'departamento_id' => Departamento::factory(),
        ];
    }

    public function withTecnico(?Tecnico $tecnico = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tecnico_id' => $tecnico?->id ?? Tecnico::factory(),
        ]);
    }

    public function withEquipo(?Equipo $equipo = null): static
    {
        return $this->state(fn (array $attributes) => [
            'equipo_id' => $equipo?->id ?? Equipo::factory(),
        ]);
    }
}
