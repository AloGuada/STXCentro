<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $renames = [
        'prod.cortes.ver' => 'prod.destajos.ver',
        'prod.cortes.crear' => 'prod.destajos.crear',
        'prod.cortes.cerrar' => 'prod.destajos.cerrar',
    ];

    public function up(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();

        foreach ($this->renames as $old => $new) {
            // Renombrar preserva las asignaciones en role_has_permissions (van por permission_id).
            $permissions::query()->where('name', $old)->update(['name' => $new]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();

        foreach ($this->renames as $old => $new) {
            $permissions::query()->where('name', $new)->update(['name' => $old]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
