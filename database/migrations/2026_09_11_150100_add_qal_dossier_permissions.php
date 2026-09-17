<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El dosier: ver y descargar, y editar —plantillas, dosieres de obra y
     * los PDF que se suben a cada sección—. El inspector sólo lo consulta; lo
     * arma la jefatura de calidad.
     *
     * @var list<string>
     */
    private const NUEVOS = ['qal.dossier.ver', 'qal.dossier.editar'];

    public function up(): void
    {
        foreach (self::NUEVOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::NUEVOS);
        }

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()?->givePermissionTo('qal.dossier.ver');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NUEVOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
