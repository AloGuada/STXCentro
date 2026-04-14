<?php

namespace App\Models\Dg;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'dg_reportes';

    /** @var list<string> */
    protected $fillable = [
        'carpeta_id',
        'anio',
        'semana',
        'creado_por_id',
        'observaciones',
    ];

    /** @var list<string> */
    protected $appends = ['etiqueta_semana'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'semana' => 'integer',
        ];
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'carpeta_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(ReporteArchivo::class, 'reporte_id');
    }

    public function getEtiquetaSemanaAttribute(): string
    {
        return sprintf('S%02d · %d', $this->semana, $this->anio);
    }
}
