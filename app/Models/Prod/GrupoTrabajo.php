<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoTrabajo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoTrabajoFactory> */
    use HasFactory;

    protected $table = 'prod_grupos_trabajo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'linea',
        'modulo',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linea' => 'integer',
            'modulo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(GrupoEmpleado::class, 'grupo_trabajo_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'grupo_trabajo_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'grupo_trabajo_id');
    }
}
