<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\ResultadoPunto;
use App\Models\Media;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\Junta;
use App\Models\Qal\JuntaPunto;
use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use Illuminate\Support\Facades\Storage;

/**
 * La ficha de Registros: lo único de la pantalla que enseña una inspección
 * entera, con los nombres que usa calidad al hablar.
 *
 * Trae el historial de la pieza (RF-18.4): una pieza reinspeccionada es una
 * historia, no hechos sueltos, y el rechazo que explica por qué hoy está
 * liberada tiene que estar a un clic.
 */
class FichaDeRegistro
{
    /**
     * @return array<string, mixed>
     */
    public function deInspeccion(Inspeccion $inspeccion): array
    {
        $inspeccion->load([
            'obra:id,no,descripcion', 'inspector.usuario:id,name', 'capturista:id,name', 'tipoPieza', 'equipo',
            'operador', 'responsable', 'supervisorPintura', 'soldador', 'puntos.punto', 'defectos.defecto',
            'muestreo', 'juntas.soldador', 'juntas.puntos.punto', 'juntas.cordon:id,modelo_marca_id', 'pintura.lecturas', 'adherencia.tiras',
            'adherencia.fotos',
        ]);

        $muestreo = $inspeccion->muestreo;
        $pintura = $inspeccion->pintura;
        $adherencia = $inspeccion->adherencia;

        return [
            'id' => $inspeccion->id,
            'folio' => $inspeccion->folio,
            'titulo' => $inspeccion->marca.' · '.$inspeccion->fase->value.($inspeccion->consecutivo ? " · #{$inspeccion->consecutivo}" : ''),
            'nota' => trim(($inspeccion->obra?->no ?? '').' · inspección '.$inspeccion->numero_inspeccion, ' ·'),
            'estatus' => $inspeccion->estatus->value,
            'cabecera' => $this->filas([
                'Folio' => $inspeccion->folio,
                'Fecha' => $inspeccion->fecha->toDateString(),
                'Semana' => "{$inspeccion->semana} / {$inspeccion->anio}",
                'Transformación' => $inspeccion->fase->etiqueta(),
                'Sub-etapa' => $inspeccion->subetapa?->etiqueta(),
                'Tipo (1ª)' => $inspeccion->subtipo?->etiqueta(),
                'Obra' => $inspeccion->obra ? trim("{$inspeccion->obra->no} — {$inspeccion->obra->descripcion}", ' —') : null,
                'Marca' => $inspeccion->marca,
                'Lote' => $inspeccion->lote,
                'QR' => $inspeccion->qr,
                '# de pieza' => $inspeccion->consecutivo,
                'Piezas en el lote' => $inspeccion->cantidad_lote,
                'Tipo de pieza' => $inspeccion->tipoPieza ? "{$inspeccion->tipoPieza->prefijo} — {$inspeccion->tipoPieza->descripcion}" : null,
                'Peso (kg)' => $this->numero($inspeccion->kg),
                'Folio Strumis' => $inspeccion->folio_strumis,
                '# Inspección' => $inspeccion->numero_inspeccion,
                'Línea' => $inspeccion->linea,
                'Módulo' => $inspeccion->modulo,
                'Equipo' => $inspeccion->equipo?->nombre,
                'Operador' => $inspeccion->operador?->nombre,
                'Responsable del módulo' => $inspeccion->responsable?->nombre,
                'Supervisor de pintura' => $inspeccion->supervisorPintura?->nombre,
                'Soldador principal' => $inspeccion->soldador ? trim("{$inspeccion->soldador->nombre} ({$inspeccion->soldador->clave})", ' ()') : null,
                'Avance IV-50' => $inspeccion->avance_iv,
                'Avance IS-60' => $inspeccion->avance_is,
                'Observaciones' => $inspeccion->observaciones,
                'Inspector' => $inspeccion->inspector?->usuario?->name,
                'Capturó' => $inspeccion->capturista?->name,
                'Registrado' => $inspeccion->capturado_en->format('Y-m-d H:i'),
            ]),
            'puntos' => $inspeccion->puntos
                ->sortBy('punto.orden')
                ->groupBy('punto.seccion')
                ->map(fn ($respuestas, string $seccion): array => [
                    'seccion' => $seccion,
                    'puntos' => $respuestas->map(fn (InspeccionPunto $respuesta): array => [
                        'etiqueta' => $respuesta->punto->etiqueta,
                        'valor' => $respuesta->valor_texto ?? $this->numero($respuesta->valor_numerico),
                        'resultado' => $respuesta->resultado?->value,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'defectos' => $inspeccion->defectos->map(fn ($defecto): array => [
                'nombre' => $defecto->defecto->nombre,
                'cantidad' => $defecto->cantidad,
            ])->values()->all(),
            'muestreo' => $muestreo ? [
                'tamano_lote' => $muestreo->tamano_lote,
                'nivel' => $muestreo->nivel->etiqueta(),
                'muestra' => $muestreo->muestra,
                'aceptacion' => $muestreo->aceptacion,
                'rechazo' => $muestreo->rechazo,
                'conformes' => $muestreo->conformes,
                'rechazadas' => $muestreo->rechazadas,
                'veredicto' => $muestreo->veredicto?->value,
                'disposicion' => $muestreo->disposicion,
                'detalle_fallas' => $muestreo->detalle_fallas,
            ] : null,
            // La marca del modelo sobre cuyos cordones se capturaron las juntas:
            // con ella la ficha imprime la hoja del mapeo pintada con el
            // resultado de cada una. Nula si las juntas se numeraron a mano.
            'modelo_marca_id' => $inspeccion->juntas
                ->first(fn (Junta $junta): bool => $junta->cordon_id !== null)
                ?->cordon?->modelo_marca_id,
            'juntas' => $inspeccion->juntas->map(fn (Junta $junta): array => [
                'identificador' => $junta->identificador,
                'cordon_id' => $junta->cordon_id,
                'tipo' => $junta->tipo->etiqueta(),
                'soldador' => $junta->soldador ? "{$junta->soldador->nombre} ({$junta->soldador->clave})" : null,
                'espesor_requerido_mm' => $this->numero($junta->espesor_requerido_mm),
                'espesor_medido_mm' => $this->numero($junta->espesor_medido_mm),
                'espesor_cumple' => $junta->espesor_cumple,
                'es_empate' => $junta->es_empate,
                'intento' => $junta->intento,
                'resultado' => $junta->resultado->value,
                'defectos' => $junta->puntos
                    ->filter(fn (JuntaPunto $punto): bool => $punto->resultado === ResultadoPunto::NoOk)
                    ->map(fn (JuntaPunto $punto): string => $punto->punto->etiqueta)
                    ->values()
                    ->all(),
            ])->values()->all(),
            'pintura' => $pintura ? [
                'espesor_requerido_mils' => $this->numero($pintura->espesor_requerido_mils),
                'metodo' => $pintura->metodo,
                'area_m2' => $this->numero($pintura->area_m2),
                'mediciones_visibles' => $pintura->mediciones_visibles,
                'promedio_mils' => $this->numero($pintura->promedio_mils),
                'cumple' => $pintura->cumple,
                'mediciones_bajas' => $pintura->mediciones_bajas ?? [],
                'revision' => $pintura->revision,
                'accion' => $pintura->accion,
                'lecturas' => $pintura->lecturas
                    ->groupBy('medicion')
                    ->sortKeys()
                    ->map(fn ($lecturas, int $medicion): array => [
                        'medicion' => $medicion,
                        'valores' => $lecturas->sortBy('lectura')->map(fn ($lectura) => $this->numero($lectura->valor_mils))->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'adherencia' => $adherencia ? [
                'metodo' => $adherencia->metodo,
                'resultado' => $adherencia->resultado,
                'tiras' => $adherencia->tiras->pluck('clasificacion')->all(),
                'fotos' => $adherencia->fotos->map(fn (Media $foto): array => [
                    'id' => $foto->id,
                    'nombre' => $foto->nombre_original,
                    'url' => Storage::disk('public')->url($foto->path),
                    'esImagen' => str_starts_with((string) $foto->mime, 'image/'),
                ])->values()->all(),
            ] : null,
            'historial' => $inspeccion->historialDeLaPieza()
                ->orderBy('fecha')
                ->orderBy('id')
                ->get(['id', 'folio', 'fase', 'subetapa', 'numero_inspeccion', 'fecha', 'estatus'])
                ->map(fn (Inspeccion $previa): array => [
                    'id' => $previa->id,
                    'folio' => $previa->folio,
                    'etapa' => $previa->fase->value.($previa->subetapa ? ' · '.$previa->subetapa->etiqueta() : ''),
                    'numero_inspeccion' => $previa->numero_inspeccion,
                    'fecha' => $previa->fecha->toDateString(),
                    'estatus' => $previa->estatus->value,
                ])
                ->all(),
            // Sólo la última inspección de la pieza en esa etapa, y si no quedó
            // liberada: reinspeccionar una intermedia contaría el mismo
            // rechazo dos veces.
            'puedeReinspeccionar' => $inspeccion->estatus !== EstatusInspeccion::Liberado
                && ! $inspeccion->mismaPiezaYEtapa()->where('numero_inspeccion', '>', $inspeccion->numero_inspeccion)->exists(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deSublote(Sublote $sublote): array
    {
        $sublote->load([
            'lote.obra:id,no,descripcion', 'inspector.usuario:id,name', 'capturista:id,name', 'responsable',
            'soldador', 'defectos.defecto',
        ]);

        $lote = $sublote->lote;
        $grupo = Sublote::query()
            ->where(fn ($consulta) => $consulta->where('id', $sublote->grupoId())->orWhere('sublote_origen_id', $sublote->grupoId()))
            ->orderBy('numero_inspeccion')
            ->get();

        return [
            'id' => $sublote->id,
            'titulo' => "{$lote->marca} · entrega de {$sublote->unidades} unidades",
            'nota' => trim(($lote->obra?->no ?? '')." · inspección {$sublote->numero_inspeccion} de {$grupo->count()}", ' ·'),
            'veredicto' => $sublote->veredicto?->value,
            'liberado' => $sublote->liberado(),
            'sinDisposicion' => $sublote->sinDisposicion(),
            'cabecera' => $this->filas([
                'Fecha' => $sublote->fecha->toDateString(),
                'Semana' => "{$sublote->semana} / {$sublote->anio}",
                'Transformación' => $sublote->fase->etiqueta(),
                'Obra' => $lote->obra ? trim("{$lote->obra->no} — {$lote->obra->descripcion}", ' —') : null,
                'Marca del lote' => $lote->marca,
                'Descripción' => $lote->descripcion,
                'Unidades del plano' => $lote->total_unidades,
                'Unidades de esta entrega' => $sublote->unidades,
                'Peso por unidad (kg)' => $this->numero($lote->kg_unitario),
                'Elementos por unidad' => $lote->elementos_unitarios,
                'Nivel de muestreo' => $sublote->nivel->etiqueta(),
                'Muestra' => $sublote->muestra,
                'Criterio' => "acepta con ≤ {$sublote->aceptacion} · rechaza con ≥ {$sublote->rechazo}",
                'Conformes' => $sublote->conformes,
                'Rechazadas' => $sublote->rechazadas,
                'Veredicto' => $sublote->veredicto ? mb_strtoupper($sublote->veredicto->value) : 'En curso',
                'Disposición' => $sublote->disposicion,
                'Línea' => $sublote->linea,
                'Módulo' => $sublote->modulo,
                'Responsable del módulo' => $sublote->responsable?->nombre,
                // En 3ª la entrega llega pintada: el soldador no es de esta revisión.
                'Soldador' => $sublote->fase === FaseTransformacion::Segunda ? $sublote->soldador?->nombre : null,
                'Observaciones' => $sublote->observaciones,
                'Inspector' => $sublote->inspector?->usuario?->name,
                'Capturó' => $sublote->capturista?->name,
                'Registrado' => $sublote->capturado_en->format('Y-m-d H:i'),
            ]),
            'rechazadas' => $sublote->defectos
                ->groupBy('unidad')
                ->sortKeys()
                ->map(fn ($defectos, int $unidad): array => [
                    'unidad' => $unidad,
                    'defectos' => $defectos->map(fn (SubloteDefecto $defecto): string => $defecto->defecto->ambito->etiqueta().': '.$defecto->defecto->nombre)->values()->all(),
                ])
                ->values()
                ->all(),
            'historial' => $grupo->map(fn (Sublote $inspeccion): array => [
                'id' => $inspeccion->id,
                'numero_inspeccion' => $inspeccion->numero_inspeccion,
                'fecha' => $inspeccion->fecha->toDateString(),
                'veredicto' => $inspeccion->veredicto?->value,
                'muestra' => $inspeccion->muestra,
                'rechazadas' => $inspeccion->rechazadas,
                'disposicion' => $inspeccion->disposicion,
            ])->values()->all(),
            'puedeReinspeccionar' => $grupo->last()?->is($sublote) && ! $sublote->liberado(),
            // La primera inspección no se borra mientras tenga reinspecciones:
            // el grupo perdería la que mide el FPY.
            'tieneReinspecciones' => $sublote->sublote_origen_id === null && $grupo->count() > 1,
        ];
    }

    /**
     * Sólo las filas con valor: un campo vacío en la ficha haría creer que se
     * dejó sin capturar algo que no aplica a esa fase.
     *
     * @param  array<string, mixed>  $pares
     * @return list<array{etiqueta: string, valor: string}>
     */
    private function filas(array $pares): array
    {
        $filas = [];

        foreach ($pares as $etiqueta => $valor) {
            if ($valor !== null && $valor !== '') {
                $filas[] = ['etiqueta' => $etiqueta, 'valor' => (string) $valor];
            }
        }

        return $filas;
    }

    private function numero(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = (string) $valor;

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }
}
