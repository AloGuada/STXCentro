<?php

namespace App\Models\Cob;

use App\Models\Obra;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoArchivo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cob_documento_archivos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'seccion_id',
        'carpeta_id',
        'nombre_original',
        'path',
        'mime',
        'size',
        'subido_por_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(DocumentoSeccion::class, 'seccion_id');
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(DocumentoCarpeta::class, 'carpeta_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por_id');
    }
}
