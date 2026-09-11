<?php

namespace App\Services\Qal\Dosier;

use App\Models\Qal\Dossier;
use App\Models\Qal\DossierArchivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el dosier para entregar: portada, índice y, por cada sección con
 * documentos, una hoja separadora seguida de sus PDF en su orden. Todo pasa
 * por `UnidorDePdf`, el motor que está por decidirse.
 *
 * Los PDF que el motor no puede abrir —o que ya no están en el disco— quedan
 * fuera y se nombran al pie del índice: el dosier sale igual y dice qué le
 * falta, en vez de fallar entero por un escaneo.
 *
 * Las notas de las secciones son para quien arma el dosier; no se imprimen.
 */
class GeneradorDeDosier
{
    public function __construct(
        private UnidorDePdf $unidor,
        private ArbolDeSecciones $arboles,
    ) {}

    /**
     * @return array{ruta: string, fuera: list<string>}
     */
    public function descargar(Dossier $dossier): array
    {
        $dossier->load(['obra.cliente', 'secciones.archivos']);
        $disco = Storage::disk(DossierArchivo::DISCO);
        $archivos = $dossier->secciones->mapWithKeys(fn ($seccion): array => [$seccion->id => $seccion->archivos]);
        $obra = trim(($dossier->obra?->no ?? '').' — '.($dossier->obra?->descripcion ?? ''), ' —');

        $indice = [];
        $fuera = [];
        $partes = [];

        foreach ($this->arboles->aplanar($dossier->arbol()) as $seccion) {
            /** @var Collection<int, DossierArchivo> $propios */
            $propios = $archivos[$seccion['id']] ?? collect();
            $incluidos = $propios->filter(fn (DossierArchivo $archivo): bool => $archivo->compatible && $disco->exists($archivo->path));

            foreach ($propios->diff($incluidos) as $archivo) {
                $fuera[] = "{$seccion['numero']} · {$archivo->nombre_original}";
            }

            $indice[] = [...$seccion, 'archivos' => $incluidos->count(), 'fuera' => $propios->count() - $incluidos->count()];

            if ($incluidos->isNotEmpty()) {
                $partes[] = ['separador' => $seccion, 'archivos' => $incluidos->map(fn (DossierArchivo $a): string => $disco->path($a->path))->values()->all()];
            }
        }

        $temporales = [];

        try {
            $rutas = [$this->temporal($temporales, Pdf::loadView('pdf.qal.dosier.portada', [
                'obra' => $obra,
                'datos' => array_filter([
                    'Cliente' => $dossier->obra?->cliente?->nombre,
                    'Tipo de contrato' => $dossier->obra?->tipo_contrato,
                    'Plantilla' => $dossier->plantilla_nombre,
                    'Estatus' => $dossier->estatus->etiqueta(),
                    'Fecha' => ($dossier->entregado_at ?? now())->format('d/m/Y'),
                ]),
                'indice' => $indice,
                'fuera' => $fuera,
            ])->setPaper('letter')->output())];

            foreach ($partes as $parte) {
                $rutas[] = $this->temporal($temporales, Pdf::loadView('pdf.qal.dosier.separador', [
                    'seccion' => $parte['separador'],
                    'obra' => $obra,
                ])->setPaper('letter')->output());
                array_push($rutas, ...$parte['archivos']);
            }

            $destino = tempnam(sys_get_temp_dir(), 'dosier_').'.pdf';
            $this->unidor->unir($rutas, $destino);
        } finally {
            foreach ($temporales as $temporal) {
                @unlink($temporal);
            }
        }

        return ['ruta' => $destino, 'fuera' => $fuera];
    }

    /**
     * @param  list<string>  $temporales
     */
    private function temporal(array &$temporales, string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'dosier_parte_').'.pdf';
        file_put_contents($ruta, $contenido);
        $temporales[] = $ruta;

        return $ruta;
    }
}
