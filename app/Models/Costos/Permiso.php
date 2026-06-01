<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\PermisoFactory>
 */
class Permiso extends Model
{
    use HasFactory;

    protected $table = 'costos_permisos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'nivel',
        'tipo_aprobacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
        ];
    }

    public function aprobacionesDepartamento(): HasMany
    {
        return $this->hasMany(AprobacionDepartamento::class);
    }
}
