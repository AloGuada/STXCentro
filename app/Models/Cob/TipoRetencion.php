<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoRetencion extends Model
{
    use HasFactory;

    protected $table = 'cob_tipos_retenciones';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function retenciones(): HasMany
    {
        return $this->hasMany(Retencion::class, 'tipo_retencion_id');
    }
}
