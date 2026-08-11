<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permisos de las tres pantallas nuevas: ubicaciones, inventarios cíclicos
     * y la hoja de códigos de barras.
     *
     * `alm.conteos.capturar` va aparte de `.ver` porque teclear lo contado es
     * lo que después se convierte en un ajuste de existencias; y `.cerrar`
     * aparte de `.capturar` porque cerrar es lo que dispara ese ajuste. Quien
     * cuenta no necesariamente es quien autoriza la corrección.
     *
     * @var list<string>
     */
    private array $permisos = [
        'alm.ubicaciones.ver',
        'alm.ubicaciones.crear',
        'alm.ubicaciones.editar',
        'alm.conteos.ver',
        'alm.conteos.crear',
        'alm.conteos.capturar',
        'alm.conteos.cerrar',
        'alm.etiquetas.ver',
    ];

    /**
     * Se dan a super-admin en la misma migración: el menú se pinta con los
     * permisos del usuario, así que un permiso nuevo sin asignar deja la
     * pantalla invisible para todos.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();

        $superAdmin?->givePermissionTo($this->permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
