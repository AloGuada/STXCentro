<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * La captura de la recepción se mudó a Almacén y ahora la gatea
 * `alm.entradas.crear`. Sin esto, los roles que hoy reciben (almacén y costos,
 * que tenían `costos.entregas.crear`) se quedarían sin poder recibir el día del
 * despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permiso = Permission::firstOrCreate(['name' => 'alm.entradas.crear', 'guard_name' => 'web']);

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'costos.entregas.crear'))->get();

        foreach ($roles as $rol) {
            $rol->givePermissionTo($permiso);
        }
    }

    public function down(): void
    {
        // El permiso es anterior a esta migración: solo se revierte el alcance
        // que ella amplió, y solo donde el rol conserva el permiso viejo.
        $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'costos.entregas.crear'))->get();

        foreach ($roles as $rol) {
            $rol->revokePermissionTo('alm.entradas.crear');
        }
    }
};
