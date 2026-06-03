<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoCarpeta extends Model
{
    use HasFactory;

    protected $table = 'cob_documento_carpetas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'seccion_id',
        'parent_id',
        'nombre',
        'orden',
    ];

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(DocumentoSeccion::class, 'seccion_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('nombre');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(DocumentoArchivo::class, 'carpeta_id');
    }
}
