<?php

namespace App\Models\Qal;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * La prueba de adherencia de una pieza pintada (F-STX-CA-08, ASTM D3359), con
 * sus tres tiras y las fotos que van al dossier.
 */
class Adherencia extends Model
{
    protected $table = 'qal_adherencia';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'metodo',
        'resultado',
    ];

    /**
     * @return BelongsTo<Inspeccion, $this>
     */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'inspeccion_id');
    }

    /**
     * @return HasMany<AdherenciaTira, $this>
     */
    public function tiras(): HasMany
    {
        return $this->hasMany(AdherenciaTira::class, 'adherencia_id')->orderBy('orden');
    }

    /**
     * La evidencia de las tiras: sustituye al escaneo del formato.
     *
     * @return MorphMany<Media, $this>
     */
    public function fotos(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
