<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoFactory> */
    use HasFactory;

    protected $table = 'prod_grupos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
    ];

    public function empleados(): HasMany
    {
        return $this->hasMany(EmpleadoGrupo::class, 'grupo_id');
    }

    public function fabricados(): HasMany
    {
        return $this->hasMany(Fabricado::class, 'dest_grupo_id');
    }

    public function pagosExtra(): HasMany
    {
        return $this->hasMany(PagoExtra::class, 'dest_grupo_id');
    }
}
