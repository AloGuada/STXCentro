<?php

namespace App\Models\Drive;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Externo extends Authenticatable
{
    use HasFactory;

    protected $table = 'drive_externos';

    protected string $guard_name = 'externo';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'telefono',
        'empresa',
        'activo',
        'ultimo_acceso',
        'created_by',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function carpetas(): BelongsToMany
    {
        return $this->belongsToMany(Carpeta::class, 'drive_carpeta_accesos', 'externo_id', 'carpeta_id')
            ->withTimestamps();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }
}
