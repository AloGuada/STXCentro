<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El tipo de contrato ahora solo admite 'precio_alzado' / 'precio_unitario'.
        // Normaliza valores legacy que entraron por importaciones/seeds previos.
        DB::table('obras')->where('tipo_contrato', 'unitario')->update(['tipo_contrato' => 'precio_unitario']);
        DB::table('obras')->where('tipo_contrato', 'alzado')->update(['tipo_contrato' => 'precio_alzado']);
    }

    public function down(): void
    {
        // No se revierte: la forma canónica es 'precio_*'.
    }
};
