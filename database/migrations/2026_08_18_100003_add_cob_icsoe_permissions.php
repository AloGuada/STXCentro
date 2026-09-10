<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos del módulo ICSOE. Se asignan aquí mismo a los roles porque
 * producción no corre seeders: crearlos sin asignarlos dejaría la entrada del
 * menú invisible para todos.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permisos = [
        'cob.icsoe.ver',
        'cob.icsoe.crear',
        'cob.icsoe.editar',
        'cob.icsoe.eliminar',
        'cob.icsoe.verificar',
        'cob.icsoe-sbc.ver',
        'cob.icsoe-sbc.editar',
    ];

    public function up(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $roles = app(PermissionRegistrar::class)->getRoleClass();

        foreach ($this->permisos as $permiso) {
            $permissions::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['admin-cobranza', 'super-admin'] as $nombreRol) {
            $roles::query()
                ->where('name', $nombreRol)
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo($this->permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::query()->whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
