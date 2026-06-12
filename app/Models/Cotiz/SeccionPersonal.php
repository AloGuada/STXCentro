<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Celda de la matriz personal × fase de una sección de montaje (cantidad de personas).
 *
 * @use HasFactory<\Database\Factories\Cotiz\SeccionPersonalFactory>
 */
class SeccionPersonal extends Model
{
    use HasFactory;

    protected $table = 'cotiz_seccion_personal';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seccion_id',
        'fase_id',
        'categoria_id',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(SeccionMontaje::class, 'seccion_id');
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseMontaje::class, 'fase_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(PersonalCategoria::class, 'categoria_id');
    }
}
