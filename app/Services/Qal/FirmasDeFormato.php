<?php

namespace App\Services\Qal;

use App\Enums\Qal\OrigenFirmante;
use App\Models\Qal\Firmante;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;

/**
 * Las firmas que lleva un formato de Calidad, en el orden del catálogo.
 *
 * El lugar «creador» toma a quien elaboró la hoja; el de persona fija, a quien
 * se eligió en el catálogo. La rúbrica es la que cada usuario dibujó en «Mi
 * firma»; si no la ha dibujado —o no hay persona— el lugar sale con la raya en
 * blanco para firmarse a mano, nunca se omite: el formato oficial la trae.
 */
class FirmasDeFormato
{
    /**
     * @return list<array{etiqueta: string, cargo: string, nombre: string|null, rubrica: string|null}>
     */
    public function para(?Usuario $creador): array
    {
        return Firmante::query()
            ->with('usuario:id,name,firma_path')
            ->orderBy('orden')
            ->get()
            ->map(function (Firmante $firmante) use ($creador): array {
                $persona = $firmante->origen === OrigenFirmante::Creador ? $creador : $firmante->usuario;

                return [
                    'etiqueta' => $firmante->etiqueta,
                    'cargo' => $firmante->cargo,
                    'nombre' => $persona?->name,
                    'rubrica' => $this->rubrica($persona),
                ];
            })
            ->all();
    }

    /**
     * Ruta en disco, que es lo que dompdf lee; null si el archivo no está.
     */
    private function rubrica(?Usuario $persona): ?string
    {
        if (! $persona?->firma_path) {
            return null;
        }

        $disco = Storage::disk('public');

        return $disco->exists($persona->firma_path) ? $disco->path($persona->firma_path) : null;
    }
}
