<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Cob\Adenda;
use App\Models\Cob\Anticipo;
use App\Models\Cob\Comparativo;
use App\Models\Cob\ConfiguracionDocumento;
use App\Models\Cob\Contacto;
use App\Models\Cob\Deduccion;
use App\Models\Cob\Disputa;
use App\Models\Cob\DocumentoEstimacion;
use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionEstadoHistorial;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Evento;
use App\Models\Cob\Partida;
use App\Models\Cob\Penalizacion;
use App\Models\Cob\Retencion;
use App\Models\Cob\TipoRetencion;
use App\Models\Obra;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CobBillbookSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const MONEDA_MAP = [
        'pesos' => 'MXN',
        'peso' => 'MXN',
        'mxn' => 'MXN',
        'MXN' => 'MXN',
        'dolares' => 'USD',
        'dolar' => 'USD',
        'usd' => 'USD',
        'USD' => 'USD',
    ];

    private function mapMoneda(?string $moneda): string
    {
        if (! $moneda) {
            return 'MXN';
        }

        return self::MONEDA_MAP[strtolower(trim($moneda))] ?? 'MXN';
    }

    public function run(): void
    {
        $bb = DB::connection('billbook');

        $this->command->info('Limpiando tablas de cobranza...');
        $this->limpiarDatos();

        $this->command->info('Migrando clientes...');
        $clienteMap = $this->migrarClientes($bb);

        $this->command->info('Migrando contactos...');
        $contactoMap = $this->migrarContactos($bb, $clienteMap);
        $this->actualizarContactoPrincipal($bb, $clienteMap, $contactoMap);

        $this->command->info('Migrando proyectos → obras...');
        $obraMap = $this->migrarProyectos($bb, $clienteMap);

        $this->command->info('Migrando partidas...');
        $this->migrarPartidas($bb, $obraMap);

        $this->command->info('Migrando tipos de retenciones...');
        $tipoRetencionMap = $this->migrarTiposRetenciones($bb);

        $this->command->info('Migrando estimaciones...');
        $estimacionMap = $this->migrarEstimaciones($bb, $obraMap);

        $this->command->info('Migrando pagos de estimaciones...');
        $this->migrarEstimacionesPagos($bb, $estimacionMap);

        $this->command->info('Migrando historial de estados...');
        $this->migrarEstimacionEstadoHistorial($bb, $estimacionMap);

        $this->command->info('Migrando retenciones...');
        $this->migrarRetenciones($bb, $estimacionMap, $tipoRetencionMap);

        $this->command->info('Migrando anticipos...');
        $this->migrarAnticipos($bb, $obraMap);

        $this->command->info('Migrando adendas...');
        $this->migrarAdendas($bb, $obraMap);

        $this->command->info('Migrando comparativos...');
        $this->migrarComparativos($bb, $obraMap);

        $this->command->info('Migrando deducciones...');
        $this->migrarDeducciones($bb, $obraMap);

        $this->command->info('Migrando disputas...');
        $this->migrarDisputas($bb, $obraMap);

        $this->command->info('Migrando penalizaciones...');
        $this->migrarPenalizaciones($bb, $obraMap);

        $this->command->info('Migrando eventos...');
        $this->migrarEventos($bb, $obraMap);

        $this->command->info('Migrando configuracion de documentos...');
        $configDocMap = $this->migrarConfiguracionDocumentos($bb, $obraMap);

        $this->command->info('Migrando documentos de estimacion...');
        $this->migrarDocumentosEstimacion($bb, $estimacionMap, $configDocMap);

        $this->command->info('Migracion de billbook completada.');
    }

    private function limpiarDatos(): void
    {
        // Orden inverso a las dependencias FK (solo tablas cob_*)
        DB::table('cob_documentos_estimacion')->delete();
        DB::table('cob_configuracion_documentos')->delete();
        DB::table('cob_eventos')->delete();
        DB::table('cob_penalizaciones')->delete();
        DB::table('cob_disputas')->delete();
        DB::table('cob_deducciones')->delete();
        DB::table('cob_comparativos')->delete();
        DB::table('cob_adendas')->delete();
        DB::table('cob_anticipos')->delete();
        DB::table('cob_retenciones')->delete();
        DB::table('cob_estimacion_estado_historial')->delete();
        DB::table('cob_estimaciones_pagos')->delete();
        DB::table('cob_estimaciones')->delete();
        DB::table('cob_tipos_retenciones')->delete();
        DB::table('cob_partidas')->delete();
        DB::table('cob_contactos')->delete();
    }

    /**
     * @return array<int, int> billbook_id => mono_id
     */
    private function migrarClientes(ConnectionInterface $bb): array
    {
        $map = [];
        $rows = $bb->table('clientes')->get();
        $creados = 0;
        $existentes = 0;

        foreach ($rows as $row) {
            $cliente = $row->rfc
                ? Cliente::where('rfc', $row->rfc)->first()
                : Cliente::where('nombre', $row->nombre)->first();

            if (! $cliente) {
                $cliente = Cliente::create([
                    'nombre' => $row->nombre,
                    'rfc' => $row->rfc,
                    'direccion' => $row->direccion,
                    'telefono' => $row->telefono,
                    'email' => $row->email,
                    'activo' => $row->activo,
                ]);
                $creados++;
            } else {
                $existentes++;
            }

            $map[$row->id] = $cliente->id;
        }

        $this->command->info("  → {$creados} clientes creados, {$existentes} ya existían.");

        return $map;
    }

    /**
     * @param  array<int, int>  $clienteMap
     * @return array<int, int> billbook_id => mono_id
     */
    private function migrarContactos(ConnectionInterface $bb, array $clienteMap): array
    {
        $map = [];
        $rows = $bb->table('contactos')->get();
        $creados = 0;
        $existentes = 0;

        foreach ($rows as $row) {
            if (! isset($clienteMap[$row->cliente_id])) {
                continue;
            }

            $clienteId = $clienteMap[$row->cliente_id];
            $contacto = Contacto::where('cliente_id', $clienteId)
                ->where('nombre', $row->nombre)
                ->first();

            if (! $contacto) {
                $contacto = Contacto::create([
                    'cliente_id' => $clienteId,
                    'nombre' => $row->nombre,
                    'email' => $row->email,
                    'telefono' => $row->telefono,
                    'cargo' => $row->cargo,
                    'activo' => $row->activo,
                ]);
                $creados++;
            } else {
                $existentes++;
            }

            $map[$row->id] = $contacto->id;
        }

        $this->command->info("  → {$creados} contactos creados, {$existentes} ya existían.");

        return $map;
    }

    /**
     * @param  array<int, int>  $clienteMap
     * @param  array<int, int>  $contactoMap
     */
    private function actualizarContactoPrincipal(ConnectionInterface $bb, array $clienteMap, array $contactoMap): void
    {
        $rows = $bb->table('clientes')->whereNotNull('contacto_principal_id')->get();

        foreach ($rows as $row) {
            if (isset($clienteMap[$row->id], $contactoMap[$row->contacto_principal_id])) {
                Cliente::where('id', $clienteMap[$row->id])
                    ->update(['contacto_principal_id' => $contactoMap[$row->contacto_principal_id]]);
            }
        }
    }

    /**
     * @param  array<int, int>  $clienteMap
     * @return array<int, int> billbook_proyecto_id => mono_obra_id
     */
    private function migrarProyectos(ConnectionInterface $bb, array $clienteMap): array
    {
        $map = [];
        $rows = $bb->table('proyectos')->get();
        $creados = 0;
        $existentes = 0;

        foreach ($rows as $row) {
            if (! isset($clienteMap[$row->cliente_id])) {
                continue;
            }

            $obra = Obra::where('no', $row->nombre)
                ->where('cliente_id', $clienteMap[$row->cliente_id])
                ->first();

            if (! $obra) {
                // Desactivar el boot event que crea obraRubros automaticamente
                $obra = Obra::withoutEvents(function () use ($row, $clienteMap) {
                    return Obra::create([
                        'no' => $row->nombre,
                        'descripcion' => $row->descripcion,
                        'fecha_inicio' => $row->fecha_inicio,
                        'fecha_fin' => $row->fecha_fin,
                        'cliente_id' => $clienteMap[$row->cliente_id],
                        'tipo_contrato' => $row->tipo_contrato,
                        'monto' => $row->monto ?? 0,
                        'monto_iva' => $row->monto_iva ?? 0,
                        'anticipo' => $row->anticipo ?? 0,
                        'garantia' => $row->garantia ?? 0,
                        'peso' => $row->peso ?? 0,
                        'porcentaje_fabricacion' => $row->porcentaje_fabricacion ?? 0,
                        'porcentaje_montaje' => $row->porcentaje_montaje ?? 0,
                        'porcentaje_otros' => $row->porcentaje_otros ?? 0,
                        'descripcion_otros' => $row->descripcion_otros,
                        'activa' => $row->activa,
                    ]);
                });
                $creados++;
            } else {
                $existentes++;
            }

            $map[$row->id] = $obra->id;
        }

        $this->command->info("  → {$creados} obras creadas, {$existentes} ya existían.");

        return $map;
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarPartidas(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('partidas')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Partida::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'tipo' => $row->tipo,
                'es_adicional' => $row->es_adicional ?? false,
                'descripcion' => $row->descripcion,
                'monto' => $row->monto,
                'moneda' => $this->mapMoneda($row->moneda),
                'es_subobra' => $row->es_subobra ?? false,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} partidas migradas.");
    }

    /**
     * @return array<int, int> billbook_id => mono_id
     */
    private function migrarTiposRetenciones(ConnectionInterface $bb): array
    {
        $map = [];

        if (! $this->tableExists($bb, 'tipos_retenciones')) {
            return $map;
        }

        $rows = $bb->table('tipos_retenciones')->get();

        foreach ($rows as $row) {
            $tipo = TipoRetencion::create([
                'nombre' => $row->nombre,
                'descripcion' => $row->descripcion,
            ]);
            $map[$row->id] = $tipo->id;
        }

        $this->command->info("  → {$rows->count()} tipos de retenciones migrados.");

        return $map;
    }

    /**
     * @param  array<int, int>  $obraMap
     * @return array<int, int> billbook_id => mono_id
     */
    private function migrarEstimaciones(ConnectionInterface $bb, array $obraMap): array
    {
        $map = [];
        $rows = $bb->table('estimaciones')->get();

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            $estimacion = Estimacion::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'numero_estimacion' => $row->numero_estimacion,
                'folio' => $row->folio ?? null,
                'tipo' => $row->tipo ?? null,
                'fecha_emision' => $row->fecha_emision,
                'inicio' => $row->inicio,
                'fin' => $row->fin,
                'monto_estimado' => $row->monto_estimado,
                'monto_total' => $row->monto_total ?? 0,
                'monto_pagado' => $row->monto_pagado ?? 0,
                'moneda' => $this->mapMoneda($row->moneda),
                'estado' => $row->estado,
                'fecha_ultimo_cambio_estado' => $row->fecha_ultimo_cambio_estado,
                'comentarios' => $row->comentarios,
            ]);
            $map[$row->id] = $estimacion->id;
        }

        $this->command->info("  → {$rows->count()} estimaciones migradas.");

        return $map;
    }

    /**
     * @param  array<int, int>  $estimacionMap
     */
    private function migrarEstimacionesPagos(ConnectionInterface $bb, array $estimacionMap): void
    {
        $rows = $bb->table('estimaciones_pagos')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($estimacionMap[$row->estimacion_id])) {
                continue;
            }

            EstimacionPago::create([
                'estimacion_id' => $estimacionMap[$row->estimacion_id],
                'monto_pagado' => $row->monto_pagado,
                'fecha_pago' => $row->fecha_pago,
                'folio' => $row->folio,
                'comprobante' => $row->comprobante,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} pagos de estimaciones migrados.");
    }

    /**
     * @param  array<int, int>  $estimacionMap
     */
    private function migrarEstimacionEstadoHistorial(ConnectionInterface $bb, array $estimacionMap): void
    {
        $rows = $bb->table('estimacion_estado_historial')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($estimacionMap[$row->estimacion_id])) {
                continue;
            }

            EstimacionEstadoHistorial::create([
                'estimacion_id' => $estimacionMap[$row->estimacion_id],
                'estado_anterior' => $row->estado_anterior,
                'estado_nuevo' => $row->estado_nuevo,
                'folio' => $row->folio ?? null,
                'usuario_id' => null, // billbook usa int IDs, mono usa UUIDs
                'comentario' => $row->comentario,
                'fecha_cambio' => $row->fecha_cambio,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} entradas de historial migradas.");
    }

    /**
     * @param  array<int, int>  $estimacionMap
     * @param  array<int, int>  $tipoRetencionMap
     */
    private function migrarRetenciones(ConnectionInterface $bb, array $estimacionMap, array $tipoRetencionMap): void
    {
        if (! $this->tableExists($bb, 'retenciones')) {
            return;
        }

        $rows = $bb->table('retenciones')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($estimacionMap[$row->estimacion_id]) || ! isset($tipoRetencionMap[$row->tipo_retencion_id])) {
                continue;
            }

            Retencion::create([
                'estimacion_id' => $estimacionMap[$row->estimacion_id],
                'tipo_retencion_id' => $tipoRetencionMap[$row->tipo_retencion_id],
                'monto' => $row->monto,
                'moneda' => $this->mapMoneda($row->moneda),
            ]);
            $count++;
        }

        $this->command->info("  → {$count} retenciones migradas.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarAnticipos(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('anticipos')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Anticipo::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'folio' => $row->folio ?? null,
                'fecha_emision' => $row->fecha_emision,
                'monto' => $row->monto,
                'moneda' => $this->mapMoneda($row->moneda),
                'estado' => $row->estado,
                'comentarios' => $row->comentarios,
                'fecha_pagado' => $row->fecha_pagado ?? null,
                'comprobante' => $row->comprobante ?? null,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} anticipos migrados.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarAdendas(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('adendas')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Adenda::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'tipo' => $row->tipo,
                'descripcion' => $row->descripcion,
                'monto_modificacion' => $row->monto_modificacion,
                'fecha' => $row->fecha,
                'estado' => $row->estado,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} adendas migradas.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarComparativos(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('comparativos')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Comparativo::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'descripcion' => $row->descripcion,
                'monto_impacto' => $row->monto_impacto,
                'fecha_identificacion' => $row->fecha_identificacion,
                'estado' => $row->estado,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} comparativos migrados.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarDeducciones(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('deducciones')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Deduccion::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'descripcion' => $row->descripcion,
                'monto' => $row->monto,
                'moneda' => $this->mapMoneda($row->moneda),
                'fecha' => $row->fecha,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} deducciones migradas.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarDisputas(ConnectionInterface $bb, array $obraMap): void
    {
        $rows = $bb->table('disputas')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Disputa::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'descripcion' => $row->descripcion,
                'fecha_inicio' => $row->fecha_inicio,
                'fecha_resolucion' => $row->fecha_resolucion,
                'estado' => $row->estado,
                'resultado' => $row->resultado,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} disputas migradas.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarPenalizaciones(ConnectionInterface $bb, array $obraMap): void
    {
        if (! $this->tableExists($bb, 'penalizaciones')) {
            $this->command->warn('  → Tabla penalizaciones no existe en billbook, saltando.');

            return;
        }

        $rows = $bb->table('penalizaciones')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            Penalizacion::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'descripcion' => $row->descripcion,
                'monto' => $row->monto,
                'moneda' => $this->mapMoneda($row->moneda ?? null),
                'tipo' => $row->tipo,
                'fecha' => $row->fecha,
            ]);
            $count++;
        }

        $this->command->info("  → {$count} penalizaciones migradas.");
    }

    /**
     * @param  array<int, int>  $obraMap
     */
    private function migrarEventos(ConnectionInterface $bb, array $obraMap): void
    {
        // Primero migrar eventos sin parent (raiz)
        $rows = $bb->table('eventos')->orderBy('id')->get();
        $map = [];
        $count = 0;

        // Primera pasada: todos los eventos
        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            $evento = Evento::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'parent_id' => null, // Se actualizara en segunda pasada
                'nombre' => $row->nombre,
                'monto' => $row->monto,
                'inicio' => $row->inicio,
                'fin' => $row->fin,
                'marcado' => $row->marcado ?? false,
            ]);
            $map[$row->id] = $evento->id;
            $count++;
        }

        // Segunda pasada: actualizar parent_id
        foreach ($rows as $row) {
            if ($row->parent_id && isset($map[$row->id], $map[$row->parent_id])) {
                Evento::where('id', $map[$row->id])
                    ->update(['parent_id' => $map[$row->parent_id]]);
            }
        }

        $this->command->info("  → {$count} eventos migrados.");
    }

    /**
     * @param  array<int, int>  $obraMap
     * @return array<int, int> billbook_id => mono_id
     */
    private function migrarConfiguracionDocumentos(ConnectionInterface $bb, array $obraMap): array
    {
        $map = [];

        if (! $this->tableExists($bb, 'configuracion_documentos_estimacion')) {
            return $map;
        }

        $rows = $bb->table('configuracion_documentos_estimacion')->get();

        foreach ($rows as $row) {
            if (! isset($obraMap[$row->proyecto_id])) {
                continue;
            }

            $config = ConfiguracionDocumento::create([
                'obra_id' => $obraMap[$row->proyecto_id],
                'nombre_documento' => $row->nombre_documento,
                'descripcion' => $row->descripcion,
                'obligatorio' => $row->obligatorio,
            ]);
            $map[$row->id] = $config->id;
        }

        $this->command->info("  → {$rows->count()} configuraciones de documentos migradas.");

        return $map;
    }

    /**
     * @param  array<int, int>  $estimacionMap
     * @param  array<int, int>  $configDocMap
     */
    private function migrarDocumentosEstimacion(ConnectionInterface $bb, array $estimacionMap, array $configDocMap): void
    {
        if (! $this->tableExists($bb, 'documentos_estimacion')) {
            return;
        }

        $rows = $bb->table('documentos_estimacion')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (! isset($estimacionMap[$row->estimacion_id]) || ! isset($configDocMap[$row->configuracion_documento_id])) {
                continue;
            }

            DocumentoEstimacion::create([
                'estimacion_id' => $estimacionMap[$row->estimacion_id],
                'configuracion_documento_id' => $configDocMap[$row->configuracion_documento_id],
                'ruta_archivo' => $row->ruta_archivo,
                'fecha_subida' => $row->fecha_subida,
                'subido_por' => null, // billbook usa int IDs, mono usa UUIDs
            ]);
            $count++;
        }

        $this->command->info("  → {$count} documentos de estimacion migrados.");
    }

    private function tableExists(ConnectionInterface $connection, string $table): bool
    {
        return Schema::connection($connection->getName())->hasTable($table);
    }
}
