<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. OrdenCompra: archivo_path, pdf_formato_path, pdf_firmado_path
        $this->migrateColumn('costos_ordenes_compra', 'archivo_path', 'App\\Models\\Costos\\OrdenCompra', 'archivo');
        $this->migrateColumn('costos_ordenes_compra', 'pdf_formato_path', 'App\\Models\\Costos\\OrdenCompra', 'pdf_formato');
        $this->migrateColumn('costos_ordenes_compra', 'pdf_firmado_path', 'App\\Models\\Costos\\OrdenCompra', 'pdf_firmado');

        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['archivo_path', 'pdf_formato_path', 'pdf_firmado_path']);
        });

        // 2. Entrega: archivo_path
        $this->migrateColumn('costos_entregas', 'archivo_path', 'App\\Models\\Costos\\Entrega', 'archivo');

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropColumn('archivo_path');
        });

        // 3. Pago: ruta_comprobante
        $this->migrateColumn('costos_pagos', 'ruta_comprobante', 'App\\Models\\Costos\\Pago', 'comprobante');

        Schema::table('costos_pagos', function (Blueprint $table) {
            $table->dropColumn('ruta_comprobante');
        });

        // 4. SolicitudPago: comprobante_aprobacion_presupuesto
        $this->migrateColumn('costos_solicitudes_pago', 'comprobante_aprobacion_presupuesto', 'App\\Models\\Costos\\SolicitudPago', 'comprobante_aprobacion');

        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->dropColumn('comprobante_aprobacion_presupuesto');
        });

        // 5. Anticipo: comprobante
        $this->migrateColumn('cob_anticipos', 'comprobante', 'App\\Models\\Cob\\Anticipo', 'comprobante');

        Schema::table('cob_anticipos', function (Blueprint $table) {
            $table->dropColumn('comprobante');
        });

        // 6. EstimacionPago: comprobante
        $this->migrateColumn('cob_estimaciones_pagos', 'comprobante', 'App\\Models\\Cob\\EstimacionPago', 'comprobante');

        Schema::table('cob_estimaciones_pagos', function (Blueprint $table) {
            $table->dropColumn('comprobante');
        });

        // 7. Persona: cv_ruta
        $this->migrateColumn('rh_personas', 'cv_ruta', 'App\\Models\\Rh\\Persona', 'cv');

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn('cv_ruta');
        });

        // 8. OnboardingTarea: evidencia_ruta
        $this->migrateColumn('rh_onboarding_tareas', 'evidencia_ruta', 'App\\Models\\Rh\\OnboardingTarea', 'evidencia');

        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->dropColumn('evidencia_ruta');
        });
    }

    public function down(): void
    {
        // Re-add columns
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->string('archivo_path')->nullable();
            $table->string('pdf_formato_path')->nullable();
            $table->string('pdf_firmado_path')->nullable();
        });

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->string('archivo_path')->nullable();
        });

        Schema::table('costos_pagos', function (Blueprint $table) {
            $table->string('ruta_comprobante')->nullable();
        });

        Schema::table('costos_solicitudes_pago', function (Blueprint $table) {
            $table->string('comprobante_aprobacion_presupuesto')->nullable();
        });

        Schema::table('cob_anticipos', function (Blueprint $table) {
            $table->string('comprobante')->nullable();
        });

        Schema::table('cob_estimaciones_pagos', function (Blueprint $table) {
            $table->string('comprobante')->nullable();
        });

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->string('cv_ruta')->nullable();
        });

        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->string('evidencia_ruta')->nullable();
        });

        // Restore data from media back to columns
        $this->restoreColumn('costos_ordenes_compra', 'archivo_path', 'App\\Models\\Costos\\OrdenCompra', 'archivo');
        $this->restoreColumn('costos_ordenes_compra', 'pdf_formato_path', 'App\\Models\\Costos\\OrdenCompra', 'pdf_formato');
        $this->restoreColumn('costos_ordenes_compra', 'pdf_firmado_path', 'App\\Models\\Costos\\OrdenCompra', 'pdf_firmado');
        $this->restoreColumn('costos_entregas', 'archivo_path', 'App\\Models\\Costos\\Entrega', 'archivo');
        $this->restoreColumn('costos_pagos', 'ruta_comprobante', 'App\\Models\\Costos\\Pago', 'comprobante');
        $this->restoreColumn('costos_solicitudes_pago', 'comprobante_aprobacion_presupuesto', 'App\\Models\\Costos\\SolicitudPago', 'comprobante_aprobacion');
        $this->restoreColumn('cob_anticipos', 'comprobante', 'App\\Models\\Cob\\Anticipo', 'comprobante');
        $this->restoreColumn('cob_estimaciones_pagos', 'comprobante', 'App\\Models\\Cob\\EstimacionPago', 'comprobante');
        $this->restoreColumn('rh_personas', 'cv_ruta', 'App\\Models\\Rh\\Persona', 'cv');
        $this->restoreColumn('rh_onboarding_tareas', 'evidencia_ruta', 'App\\Models\\Rh\\OnboardingTarea', 'evidencia');

        // Delete migrated media records
        DB::table('media')->whereIn('mediable_type', [
            'App\\Models\\Costos\\OrdenCompra',
            'App\\Models\\Costos\\Entrega',
            'App\\Models\\Costos\\Pago',
            'App\\Models\\Costos\\SolicitudPago',
            'App\\Models\\Cob\\Anticipo',
            'App\\Models\\Cob\\EstimacionPago',
            'App\\Models\\Rh\\Persona',
            'App\\Models\\Rh\\OnboardingTarea',
        ])->delete();
    }

    private function migrateColumn(string $table, string $column, string $mediableType, string $descripcion): void
    {
        $records = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get(['id', $column]);

        foreach ($records as $record) {
            DB::table('media')->insert([
                'descripcion' => $descripcion,
                'path' => $record->{$column},
                'mediable_type' => $mediableType,
                'mediable_id' => $record->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function restoreColumn(string $table, string $column, string $mediableType, string $descripcion): void
    {
        $mediaRecords = DB::table('media')
            ->where('mediable_type', $mediableType)
            ->where('descripcion', $descripcion)
            ->get(['mediable_id', 'path']);

        foreach ($mediaRecords as $media) {
            DB::table($table)->where('id', $media->mediable_id)->update([
                $column => $media->path,
            ]);
        }
    }
};
