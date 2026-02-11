<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transformador extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TransformadorFactory> */
    use HasFactory;

    protected $table = 'infra_transformadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'linea_A',
        'linea_A_max',
        'date_A',
        'linea_B',
        'linea_B_max',
        'date_B',
        'linea_C',
        'linea_C_max',
        'date_C',
        'total_1',
        'total_5',
        'lectura_5y5',
        'lectura_301',
        'lectura_302',
        'lectura_303',
        'lectura_310',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_A' => 'datetime',
            'date_B' => 'datetime',
            'date_C' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
