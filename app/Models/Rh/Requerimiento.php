<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requerimiento extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\RequerimientoFactory> */
    use HasFactory;

    protected $table = 'rh_requerimientos';

    /** @var list<string> */
    protected $fillable = [
        'descripcion',
        'valor',
    ];

    public function puestos(): BelongsToMany
    {
        return $this->belongsToMany(Puesto::class, 'rh_puesto_requerimientos', 'requerimiento_id', 'puesto_id')
            ->withTimestamps();
    }

    public function requerimientosDemostrados(): HasMany
    {
        return $this->hasMany(RequerimientoDemostrado::class, 'requerimiento_id');
    }
}
