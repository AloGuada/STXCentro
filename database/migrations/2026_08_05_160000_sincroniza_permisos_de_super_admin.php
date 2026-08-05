<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Le da a `super-admin` los permisos que se crearon después de la última
     * corrida del seeder de roles.
     *
     * `RolesSeeder` declara que super-admin los tiene todos
     * (`givePermissionTo($todos)`), pero ese seeder es destructivo y no se
     * vuelve a correr en producción. Cada permiso nuevo llega por su propia
     * migración, y esas migraciones se lo dan al rol de su módulo — nadie se
     * acordó de super-admin. El resultado se ve en pantalla: el menú se pinta
     * con los permisos del usuario, así que a un super-admin le faltaban
     * entradas de módulos enteros.
     *
     * Sólo agrega, nunca quita, así que es segura de repetir.
     */
    public function up(): void
    {
        $rol = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();

        if (! $rol) {
            return;
        }

        $permisos = app(PermissionRegistrar::class)->getPermissionClass();

        $faltantes = $permisos::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $rol->permissions()->pluck('name'))
            ->pluck('name');

        if ($faltantes->isNotEmpty()) {
            $rol->givePermissionTo($faltantes->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sin vuelta atrás: quitarle permisos a super-admin dejaría el rol peor de
     * como estaba, y no hay registro de cuáles tenía antes.
     */
    public function down(): void {}
};
