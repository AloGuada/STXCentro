<?php

namespace App\Services\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Models\Costos\Permiso;
use Illuminate\Support\Collection;

/**
 * Construye las columnas de firma para los PDF de costos (solicitud de pago y
 * comparativo de requisición/OC).
 *
 * La fuente de verdad son las aprobaciones REALES del documento (la cadena que
 * se le aplicó): una columna por nivel, ordenadas por nivel. Así el PDF refleja
 * exactamente las firmas de la cadena aunque después se hayan reconfigurado los
 * niveles del departamento. El `Permiso` de la config actual solo se usa, en la
 * medida en que exista, para rotular el nivel (descripción/rol).
 */
class FirmasPdfBuilder
{
    /**
     * @param  Collection<int, object>  $aprobaciones  Aprobaciones del documento (con `aprobador` cargado).
     * @return Collection<int, object{permiso: object, aprobador: mixed, aprobada: bool, fecha: ?string, candidatos: Collection<int, string>}>
     */
    public function build(string $tipoAprobacion, ?int $departamentoId, Collection $aprobaciones): Collection
    {
        // Etiqueta (rol) de cada nivel según la configuración vigente; es solo
        // para rotular, no filtra ni ordena las columnas.
        $permisosPorNivel = Permiso::query()
            ->where('tipo_aprobacion', $tipoAprobacion)
            ->orderBy('nivel')
            ->get()
            ->keyBy('nivel');

        return $aprobaciones
            ->groupBy('nivel')
            ->sortKeys()
            ->map(function (Collection $delNivel, $nivel) use ($permisosPorNivel) {
                $aprobada = $delNivel->first(fn ($a) => $this->estaAprobada($a));

                // Aprobadores asignados a este nivel en el documento (multiusuario).
                $candidatos = $delNivel
                    ->map(fn ($a) => $a->aprobador?->name)
                    ->filter()
                    ->unique()
                    ->values();

                // La firma adicional (ad-hoc) vive en el nivel 0 y no tiene un
                // Permiso configurado; se rotula explícitamente.
                $esAdicional = (bool) ($delNivel->first()->es_adicional ?? false) || (int) $nivel === 0;

                return (object) [
                    'permiso' => $esAdicional
                        ? (object) ['descripcion' => 'Firma adicional', 'nivel' => (int) $nivel]
                        : ($permisosPorNivel->get($nivel)
                            ?? (object) ['descripcion' => '', 'nivel' => (int) $nivel]),
                    'aprobador' => $aprobada?->aprobador,
                    'aprobada' => $aprobada !== null,
                    'fecha' => $aprobada?->fecha_respuesta?->format('d/m/Y H:i'),
                    'candidatos' => $candidatos,
                ];
            })
            ->values();
    }

    /**
     * Normaliza el estatus (enum en modelos reales, string en objetos de prueba)
     * para detectar el nivel firmado.
     */
    private function estaAprobada(object $aprobacion): bool
    {
        $estatus = $aprobacion->estatus ?? null;

        if ($estatus instanceof AprobacionEstatus) {
            $estatus = $estatus->value;
        }

        return $estatus === AprobacionEstatus::Aprobada->value;
    }
}
