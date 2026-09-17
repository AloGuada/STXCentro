<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El permiso del reporte semanal (F-STX-CA-31).
     *
     * Va aparte de `qal.dashboard.ver` aunque los dos sólo lean: el tablero es
     * la herramienta de trabajo del área —se mira todos los días y se filtra a
     * conveniencia—, y esto es el documento que sale de la empresa con folio de
     * formato y se manda a dirección y al cliente. Quien puede consultar el
     * taller no necesariamente puede emitir el reporte que lo representa.
     */
    private const PERMISO = 'qal.reporte-semanal.ver';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
