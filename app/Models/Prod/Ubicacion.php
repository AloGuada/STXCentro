<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ubicación de trabajo (línea, módulo, nave…). Sustituye a los enteros `linea`
 * y `modulo` que antes vivían sueltos en el grupo; un grupo puede usar varias.
 */
class Ubicacion extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\UbicacionFactory> */
    use HasFactory;

    protected $table = 'prod_ubicaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function gruposTrabajo(): BelongsToMany
    {
        return $this->belongsToMany(
            GrupoTrabajo::class,
            'prod_grupo_trabajo_ubicaciones',
            'ubicacion_id',
            'grupo_trabajo_id',
        );
    }
}
