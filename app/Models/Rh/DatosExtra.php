<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatosExtra extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\DatosExtraFactory> */
    use HasFactory;

    protected $table = 'rh_datos_extras';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'estado_civil',
        'hijos',
        'localidad',
        'domicilio',
        'cp',
        'nombre_padre',
        'nombre_madre',
        'cuenta_banco',
        'c_infonavit',
        'c_fonacot',
        'imss',
        'curp',
        'rfc',
        'numero_ine',
        'banco_op',
        'texto_cv',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hijos' => 'integer',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
