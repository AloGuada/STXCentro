<?php

namespace App\Models\Cob;

use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoSeccionProyecto extends Model
{
    /** @use HasFactory<\Database\Factories\Cob\DocumentoSeccionProyectoFactory> */
    use HasFactory;

    protected $table = 'cob_documento_seccion_proyecto';

    /** @var list<string> */
    protected $fillable = [
        'proyecto_id',
        'seccion_id',
        'estatus',
        'visible',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(DocumentoSeccion::class, 'seccion_id');
    }
}
