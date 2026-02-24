<?php

namespace App\Models\Cob;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoEstimacion extends Model
{
    use HasFactory;

    protected $table = 'cob_documentos_estimacion';

    /** @var list<string> */
    protected $fillable = [
        'estimacion_id',
        'configuracion_documento_id',
        'ruta_archivo',
        'fecha_subida',
        'subido_por',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_subida' => 'datetime',
        ];
    }

    public function estimacion(): BelongsTo
    {
        return $this->belongsTo(Estimacion::class, 'estimacion_id');
    }

    public function configuracionDocumento(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionDocumento::class, 'configuracion_documento_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}
