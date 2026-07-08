<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\TipoSolicitudFactory>
 */
class TipoSolicitud extends Model
{
    use HasFactory;

    protected $table = 'costos_tipo_solicitud';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'descripcion',
        'rubros',
        'saltar_verificacion_costos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rubros' => 'boolean',
            'saltar_verificacion_costos' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'tipo_solicitud_id');
    }
}
