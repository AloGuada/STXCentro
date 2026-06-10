<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\ResumenBloqueColorFactory>
 */
class ResumenBloqueColor extends Model
{
    use HasFactory;

    protected $table = 'cotiz_resumen_bloque_colores';

    protected $primaryKey = 'bloque';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bloque',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bloque' => ResumenBloque::class,
        ];
    }
}
