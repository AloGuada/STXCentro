<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
