<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Quién puede firmar como solicitante de un pedido de almacén.
     *
     * El pedido ya no queda a nombre de quien lo teclea sino del supervisor que
     * lo pide, y ese nombre es el que arrastran la salida, el préstamo y la
     * transferencia que lo surten. La lista de supervisores es quien tiene
     * este permiso, por rol o directo: el rol `supervisor` nace con él, y
     * dárselo suelto a alguien es posible sin darle el rol completo.
     */
    private const PERMISO = 'alm.pedidos.supervisar';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web'])->givePermissionTo(self::PERMISO);
        Role::where('name', 'super-admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
