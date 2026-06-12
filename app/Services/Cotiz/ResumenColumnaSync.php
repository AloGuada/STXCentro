<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ResumenColumnaTarjeta;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza las columnas del Resumen con las tarjetas de la obra: 1 columna por tarjeta,
 * nombre = descripción de la tarjeta. Borra columnas no conformes (huérfanas/duplicadas)
 * y crea las que falten. Idempotente.
 */
class ResumenColumnaSync
{
    public function sincronizar(Obra $obra): void
    {
        DB::transaction(function () use ($obra) {
            $tarjetas = $obra->tarjetas()->orderBy('id')->get(['id', 'descripcion']);
            $tarjetaIds = $tarjetas->pluck('id');

            $vistas = [];
            foreach ($obra->resumenColumnas()->with('tarjetas')->get() as $columna) {
                $links = $columna->tarjetas->pluck('tarjeta_id');
                $tarjetaId = $links->first();
                $conforme = $links->count() === 1
                    && $tarjetaIds->contains($tarjetaId)
                    && ! in_array($tarjetaId, $vistas, true);

                if ($conforme) {
                    $vistas[] = $tarjetaId;
                    $nombre = $tarjetas->firstWhere('id', $tarjetaId)->descripcion;
                    if ($columna->nombre !== $nombre) {
                        $columna->update(['nombre' => $nombre]);
                    }
                } else {
                    $columna->delete();
                }
            }

            $orden = ($obra->resumenColumnas()->max('orden') ?? 0) + 10;
            foreach ($tarjetas as $tarjeta) {
                if (in_array($tarjeta->id, $vistas, true)) {
                    continue;
                }
                $columna = $obra->resumenColumnas()->create([
                    'nombre' => $tarjeta->descripcion,
                    'orden' => $orden,
                ]);
                ResumenColumnaTarjeta::query()->create([
                    'columna_id' => $columna->id,
                    'tarjeta_id' => $tarjeta->id,
                ]);
                $orden += 10;
            }
        });
    }
}
