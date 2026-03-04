<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPuesto extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\DocumentoPuestoFactory> */
    use HasFactory;

    protected $table = 'rh_documentos_puesto';

    /** @var list<string> */
    protected $fillable = [
        'puesto_id',
        'nombre_reporte',
        'frecuencia_entrega',
        'cargo_entrega',
    ];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }
}
