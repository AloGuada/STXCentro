<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $table = 'usuarios';

    protected string $guard_name = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empleado',
        'departamento_id',
        'name',
        'email',
        'password',
        'rol',
        'firma_path',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'activo' => 'boolean',
            'fecha_baja' => 'datetime',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function departamentoComoManager(): HasOne
    {
        return $this->hasOne(Departamento::class, 'manager_usuario_id');
    }

    /**
     * @param  Builder<Usuario>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * Los que tienen el permiso, por rol o directo.
     *
     * Se consulta a mano y no con el scope de Spatie porque las tablas pivote
     * guardan `App\Models\Usuario` como tipo y el usuario autenticado es
     * `App\Models\User`: filtrar por tipo no encuentra nada. Aquí sólo cuenta
     * `model_uuid`.
     *
     * @param  Builder<Usuario>  $query
     */
    public function scopeConPermiso(Builder $query, string $permiso): void
    {
        $tablas = config('permission.table_names');

        $permisoId = \Illuminate\Support\Facades\DB::table($tablas['permissions'])
            ->where('name', $permiso)
            ->where('guard_name', 'web')
            ->select('id');

        $query->where(fn (Builder $q) => $q
            ->whereIn('id', \Illuminate\Support\Facades\DB::table($tablas['model_has_permissions'])
                ->whereIn('permission_id', $permisoId)
                ->select('model_uuid'))
            ->orWhereIn('id', \Illuminate\Support\Facades\DB::table($tablas['model_has_roles'])
                ->join($tablas['role_has_permissions'], $tablas['role_has_permissions'].'.role_id', '=', $tablas['model_has_roles'].'.role_id')
                ->whereIn($tablas['role_has_permissions'].'.permission_id', $permisoId)
                ->select($tablas['model_has_roles'].'.model_uuid')));
    }

    /**
     * A cuyo nombre puede quedar un pedido de almacén, y quién responde por un
     * préstamo. Es la lista que ofrecen esas pantallas y la que exigen al
     * guardarse.
     *
     * @param  Builder<Usuario>  $query
     */
    public function scopeSupervisoresDeAlmacen(Builder $query): void
    {
        $query->activos()->conPermiso('alm.pedidos.supervisar')->orderBy('name');
    }

    public function darDeBaja(): void
    {
        $this->forceFill([
            'activo' => false,
            'fecha_baja' => now(),
        ])->save();
    }

    public function reactivar(): void
    {
        $this->forceFill([
            'activo' => true,
            'fecha_baja' => null,
        ])->save();
    }
}
