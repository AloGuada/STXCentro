<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permisos = [
        'costos.solicitudes-pago.ver-departamento-propio',
        'costos.requisiciones.ver-departamento-propio',
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
