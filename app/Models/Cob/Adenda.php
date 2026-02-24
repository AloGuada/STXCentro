<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adenda extends Model
{
    use HasFactory;

    protected $table = 'cob_adendas';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'tipo',
        'descripcion',
        'monto_modificacion',
        'fecha',
        'estado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_modificacion' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
