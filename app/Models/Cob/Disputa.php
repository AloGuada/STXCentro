<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disputa extends Model
{
    use HasFactory;

    protected $table = 'cob_disputas';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'descripcion',
        'fecha_inicio',
        'fecha_resolucion',
        'estado',
        'resultado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_resolucion' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
