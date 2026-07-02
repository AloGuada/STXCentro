<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Model;

/**
 * Centro de costo (rubro) para el que un nivel (permiso) de un departamento
 * puede saltarse cuando hay presupuesto reservado.
 */
class OmitirRubro extends Model
{
    protected $table = 'costos_omitir_rubros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'departamento_id',
        'permiso_id',
        'rubro_id',
    ];
}
