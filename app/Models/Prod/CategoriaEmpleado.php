<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría del trabajador. Sustituye al porcentaje manual por empleado.
 *
 * `valor` es un **peso**, no dinero: el sueldo base sale de los días asistidos
 * por el salario mínimo diario, y este peso sólo reparte el excedente del
 * destajo una vez cubiertas todas las bases del grupo.
 */
class CategoriaEmpleado extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\CategoriaEmpleadoFactory> */
    use HasFactory;

    protected $table = 'prod_categorias_empleado';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'valor',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(GrupoEmpleado::class, 'categoria_empleado_id');
    }
}
