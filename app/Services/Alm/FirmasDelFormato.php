<?php

namespace App\Services\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Models\Alm\FirmaDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Arma las rayas de firma que el formato impreso lleva al pie.
 *
 * Hasta hoy cada blade traía su lista escrita a mano; ahora sale de lo que se
 * configuró en Almacén > Aprobaciones. El almacén que nadie tocó cae en la
 * plantilla del enum, que es exactamente lo que decían esos blades, así que un
 * módulo sin configurar imprime igual que antes.
 */
class FirmasDelFormato
{
    /**
     * Las firmas tal como las espera `pdf.partials.firmas`.
     *
     * @return list<array{nombre: string|null, rol: string}>
     */
    public function para(DocumentoAlm $tipo, ?int $almacenId, ?Model $documento = null): array
    {
        $configuradas = $almacenId === null ? collect() : $this->configuradas($tipo, $almacenId);

        if ($configuradas->isEmpty()) {
            return array_map(fn (array $firma): array => [
                'nombre' => $this->nombreConocido($documento, $firma['fuente']),
                'rol' => $firma['rotulo'],
            ], $tipo->firmasPorDefecto());
        }

        return $configuradas
            ->map(fn (FirmaDocumento $firma): array => [
                'nombre' => $this->deLosUsuarios($firma) ?? $this->nombreConocido($documento, $firma->fuente),
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

    /**
     * El nombre que el documento ya sabe. La fuente puede ser una relación
     * —`$salida->entregador`— o una columna suelta —`$salida->recibe_nombre`—,
     * así que se resuelve por lo que devuelva.
     */
    private function nombreConocido(?Model $documento, ?string $fuente): ?string
    {
        if ($documento === null || $fuente === null) {
            return null;
        }

        $valor = $documento->{$fuente} ?? null;

        if ($valor instanceof Model) {
            return $valor->name;
        }

        return is_string($valor) && trim($valor) !== '' ? $valor : null;
    }
}
