<?php

namespace Database\Factories\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Models\Alm\Almacen;
use App\Models\Alm\FirmaDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirmaDocumento>
 */
class FirmaDocumentoFactory extends Factory
{
    protected $model = FirmaDocumento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'documento' => DocumentoAlm::Salida,
            'orden' => 1,
            'rotulo' => fake()->randomElement(['Entregó', 'Recibió', 'Autorizó', 'Revisó']),
            'fuente' => null,
        ];
    }

    /** Un renglón que imprime el nombre que el documento ya sabe. */
    public function deFuente(string $fuente): static
    {
        return $this->state(fn (): array => ['fuente' => $fuente]);
    }

    public function deDocumento(DocumentoAlm $documento, int $orden = 1): static
    {
        return $this->state(fn (): array => ['documento' => $documento, 'orden' => $orden]);
    }
}
