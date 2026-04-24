<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los valores de media.descripcion de Costos a los canonicos de
 * App\Enums\Costos\DocumentoTipo. Solo toca rows cuyo mediable_type está
 * dentro del módulo Costos; no afecta RH, Cob, Intra ni otros módulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Factura: xml -> xml_factura, pdf -> pdf_factura
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->where('descripcion', 'xml')
            ->update(['descripcion' => 'xml_factura']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->where('descripcion', 'pdf')
            ->update(['descripcion' => 'pdf_factura']);

        // OC: archivo -> oc_archivo, pdf_formato -> oc_pdf_formato, pdf_firmado -> oc_pdf_firmado
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'archivo')
            ->update(['descripcion' => 'oc_archivo']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'pdf_formato')
            ->update(['descripcion' => 'oc_pdf_formato']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'pdf_firmado')
            ->update(['descripcion' => 'oc_pdf_firmado']);

        // Entrega: archivo -> evidencia_recepcion
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Entrega')
            ->where('descripcion', 'archivo')
            ->update(['descripcion' => 'evidencia_recepcion']);

        // Pago: comprobante -> comprobante_pago
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Pago')
            ->where('descripcion', 'comprobante')
            ->update(['descripcion' => 'comprobante_pago']);

        // SolicitudPago: comprobante_aprobacion -> solicitud_firmada
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\SolicitudPago')
            ->where('descripcion', 'comprobante_aprobacion')
            ->update(['descripcion' => 'solicitud_firmada']);
    }

    public function down(): void
    {
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->where('descripcion', 'xml_factura')
            ->update(['descripcion' => 'xml']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Factura')
            ->where('descripcion', 'pdf_factura')
            ->update(['descripcion' => 'pdf']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'oc_archivo')
            ->update(['descripcion' => 'archivo']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'oc_pdf_formato')
            ->update(['descripcion' => 'pdf_formato']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\OrdenCompra')
            ->where('descripcion', 'oc_pdf_firmado')
            ->update(['descripcion' => 'pdf_firmado']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Entrega')
            ->where('descripcion', 'evidencia_recepcion')
            ->update(['descripcion' => 'archivo']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\Pago')
            ->where('descripcion', 'comprobante_pago')
            ->update(['descripcion' => 'comprobante']);

        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Costos\\SolicitudPago')
            ->where('descripcion', 'solicitud_firmada')
            ->update(['descripcion' => 'comprobante_aprobacion']);
    }
};
