<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tanque extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TanqueFactory> */
    use HasFactory;

    protected $table = 'infra_tanques';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'pa_sistema_oxigeno',
        'presion_sistema_oxigeno',
        'presion_tanque_oxigeno',
        'lt_tanque_oxigeno',
        'kg_tanque_oxigeno',
        'pa_sistema_argon',
        'presion_sistema_argon',
        'presion_tanque_argon',
        'lt_tanque_argon',
        'kg_tanque_argon',
        'pa_sistema_co2',
        'presion_sistema_co2',
        'presion_tanque_co2',
        'lt_tanque_co2',
        'kg_tanque_co2',
        'pa_sistema_lp',
        'presion_sistema_lp',
        'presion_tanque_lp',
        'numero_tanque_lp',
        'lt_tanque_lp',
        'kg_tanque_lp',
        'observaciones',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
