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
            'nombre' => null,
        ];
    }

    /**
     * Una raya guardada sólo existe en un almacén ya configurado: si no se
     * marca, el servicio lo trata como nuevo y le imprime la plantilla.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (FirmaDocumento $firma): void {
            $firma->almacen()->whereNull('firmas_configuradas_at')->update(['firmas_configuradas_at' => now()]);
        });
    }

    /** Un renglón que ya trae escrito el nombre que va sobre la raya. */
    public function conNombre(string $nombre): static
    {
        return $this->state(fn (): array => ['nombre' => $nombre]);
    }

    public function deDocumento(DocumentoAlm $documento, int $orden = 1): static
    {
        return $this->state(fn (): array => ['documento' => $documento, 'orden' => $orden]);
    }
}
