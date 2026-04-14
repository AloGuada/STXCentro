<?php

namespace App\Models\Dg;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReporteArchivo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dg_reporte_archivos';

    /** @var list<string> */
    protected $fillable = [
        'reporte_id',
        'nombre_original',
        'path',
        'mime',
        'size',
        'subido_por_id',
        'notas',
        'notas_editado_por_id',
        'notas_actualizado_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'notas_actualizado_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por_id');
    }

    public function notasEditadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'notas_editado_por_id');
    }
}
