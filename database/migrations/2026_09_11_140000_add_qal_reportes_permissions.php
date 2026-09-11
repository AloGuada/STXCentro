<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los formatos PDF F-STX-* de Calidad. Los genera el inspector —es quien
     * los firma como «Elaboró»— y la jefatura que los revisa.
     *
     * El nombre ya existió para el mapeo 2D y se retiró con él; vuelve con
     * este significado.
     */
    public function up(): void
    {
        $permiso = Permission::firstOrCreate(['name' => 'qal.reportes.ver', 'guard_name' => 'web']);

        foreach (['super-admin', 'admin-cal', 'inspector-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'qal.reportes.ver')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
