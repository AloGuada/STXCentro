<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuestoRequerimiento extends Model
{
    protected $table = 'rh_puesto_requerimientos';

    /** @var list<string> */
    protected $fillable = [
        'puesto_id',
        'requerimiento_id',
    ];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }
}
