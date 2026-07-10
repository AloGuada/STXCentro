<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza el `tipo_proveedor` legacy (materiales/servicios/equipos/mixto,
     * que servía para inferir impuestos) a la nueva clasificación principal
     * Proveedor/Tercero/Servicio. Todos los existentes son proveedores formales.
     */
    public function up(): void
    {
        DB::table('proveedores')
            ->whereNotIn('tipo_proveedor', ['proveedor', 'tercero', 'servicio'])
            ->orWhereNull('tipo_proveedor')
            ->update(['tipo_proveedor' => 'proveedor']);
    }

    public function down(): void
    {
        // Irreversible: la información legacy (materiales/equipos/mixto) no se
        // conserva. No se revierte.
    }
};
