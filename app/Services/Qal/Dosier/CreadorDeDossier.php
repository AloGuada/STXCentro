<?php

namespace App\Services\Qal\Dosier;

use App\Enums\Qal\EstatusDossier;
use App\Models\Qal\Dossier;
use App\Models\Qal\DossierPlantilla;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * El dosier de una obra nace de una plantilla: se copia su árbol entero. Es
 * una copia y no una referencia —cambiar la plantilla después no toca los
 * dosieres que ya nacieron de ella—, y guarda el nombre de la plantilla por si
 * ésta se renombra o se desactiva.
 */
class CreadorDeDossier
{
    public function __construct(private ArbolDeSecciones $arboles) {}

    public function desdePlantilla(int $obraId, DossierPlantilla $plantilla, ?Usuario $capturista): Dossier
    {
        return DB::transaction(function () use ($obraId, $plantilla, $capturista): Dossier {
            $dossier = Dossier::create([
                'obra_id' => $obraId,
                'plantilla_id' => $plantilla->id,
                'plantilla_nombre' => $plantilla->nombre,
                'estatus' => EstatusDossier::Borrador,
                'capturista_id' => $capturista?->id,
            ]);

            $this->arboles->guardar($dossier->secciones(), $plantilla->arbol());

            return $dossier;
        });
    }
}
