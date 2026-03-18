<?php

namespace App\Models\Cal;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'cal_reportes';

    protected $fillable = [
        'plano_id',
        'strumis_id',
        'consecutivo',
        'inspector_id',
        'plantilla',
        'aprobado',
        'rechazado',
        'es_plantilla',
        'linea',
        'modulo',
        'comentario',
        'folio',
        'soldador_id',
    ];

    protected function casts(): array
    {
        return [
            'aprobado' => 'datetime',
            'rechazado' => 'datetime',
            'es_plantilla' => 'boolean',
            'linea' => 'integer',
            'modulo' => 'integer',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PiezaPlano::class, 'plano_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'inspector_id');
    }

    public function soldador(): BelongsTo
    {
        return $this->belongsTo(Soldador::class, 'soldador_id');
    }

    public function flechas(): HasMany
    {
        return $this->hasMany(Flecha::class, 'reporte_id');
    }
}
