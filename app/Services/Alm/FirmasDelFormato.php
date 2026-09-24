<?php

namespace App\Services\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Models\Alm\Almacen;
use App\Models\Alm\FirmaDocumento;
use Illuminate\Support\Collection;

/**
 * Arma las rayas de firma que el formato impreso lleva al pie.
 *
 * Hasta hoy cada blade traía su lista escrita a mano; ahora sale de lo que se
 * configuró en Almacén > Aprobaciones. El almacén que nadie tocó cae en la
 * plantilla del enum, que dice los mismos rótulos que decían esos blades; el
 * que ya se configuró imprime lo suyo, aunque haya dejado un documento sin rayas.
 *
 * Sobre la raya va lo que se haya elegido: los usuarios, o el nombre escrito a
 * mano. Nada se adivina del documento: si no se configuró, la raya va vacía y
 * se llena al firmar.
 */
class FirmasDelFormato
{
    /**
     * Las firmas tal como las espera `pdf.partials.firmas`.
     *
     * @return list<array{nombre: string|null, rol: string}>
     */
    public function para(DocumentoAlm $tipo, ?int $almacenId): array
    {
        // La plantilla es sólo para el almacén que nadie ha configurado. El
        // configurado imprime lo suyo, aunque lo suyo sea ninguna raya.
        $almacen = $almacenId === null ? null : Almacen::query()->find($almacenId, ['id', 'firmas_configuradas_at']);

        if ($almacen?->firmas_configuradas_at === null) {
            return array_map(fn (array $firma): array => [
                'nombre' => $firma['nombre'],
                'rol' => $firma['rotulo'],
            ], $tipo->firmasPorDefecto());
        }

        return $this->configuradas($tipo, $almacen->id)
            ->map(fn (FirmaDocumento $firma): array => [
                'nombre' => $this->deLosUsuarios($firma) ?? $firma->nombre,
                'rol' => $firma->rotulo,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, FirmaDocumento>
     */
    private function configuradas(DocumentoAlm $tipo, int $almacenId): Collection
    {
        return FirmaDocumento::query()
            ->with('usuarios:id,name')
            ->where('almacen_id', $almacenId)
            ->where('documento', $tipo->value)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Con varios usuarios se imprimen separados: basta con que firme
     * cualquiera de ellos.
     */
    private function deLosUsuarios(FirmaDocumento $firma): ?string
    {
        $nombres = $firma->usuarios->pluck('name')->filter();

        return $nombres->isEmpty() ? null : $nombres->implode(' / ');
    }
}
