<?php

namespace App\Models\Drive;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarpetaAcceso extends Model
{
    protected $table = 'drive_carpeta_accesos';

    /** @var list<string> */
    protected $fillable = [
        'carpeta_id',
        'externo_id',
    ];

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'carpeta_id');
    }

    public function externo(): BelongsTo
    {
        return $this->belongsTo(Externo::class, 'externo_id');
    }
}
