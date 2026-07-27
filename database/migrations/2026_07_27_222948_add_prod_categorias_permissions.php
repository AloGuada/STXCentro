<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permisos = [
        'prod.categorias.ver',
        'prod.categorias.crear',
        'prod.categorias.editar',
        'prod.categorias.eliminar',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();
    }
};
