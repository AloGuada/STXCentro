<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Actividad extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\ActividadFactory> */
    use HasFactory;

    protected $table = 'rh_actividades';

    /** @var list<string> */
    protected $fillable = [
        'puesto_id',
        'descripcion',
    ];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }
}
