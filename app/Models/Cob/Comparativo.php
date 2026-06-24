<?php

namespace App\Models\Cob;

use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comparativo extends Model
{
    use HasFactory;

    protected $table = 'cob_comparativos';

    /** @var list<string> */
    protected $fillable = [
        'proyecto_id',
        'obra_id',
        'descripcion',
        'monto_impacto',
        'fecha_identificacion',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_identificacion' => 'date',
            'monto_impacto' => 'decimal:2',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
