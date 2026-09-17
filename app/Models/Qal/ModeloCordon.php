<?php

namespace App\Models\Qal;

use App\Enums\Qal\TipoCordon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un cordón de soldadura detectado en una marca del modelo: la plantilla sobre
 * la que el inspector reporta la junta.
 *
 * @use HasFactory<\Database\Factories\Qal\ModeloCordonFactory>
 */
class ModeloCordon extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'qal_modelo_cordones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'modelo_marca_id',
        'numero',
        'tipo',
        'junta',
        'piezas',
        'largo_mm',
        'ancho_mm',
        'angulo',
        't1_mm',
        't2_mm',
        'cateto_min_mm',
        'cateto_max_mm',
        'garganta_min_mm',
        'preparacion',
        'avisos',
        'centro',
        'puntos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCordon::class,
            'numero' => 'integer',
            'piezas' => 'array',
            'largo_mm' => 'decimal:1',
            'ancho_mm' => 'decimal:1',
            'angulo' => 'decimal:1',
            't1_mm' => 'decimal:2',
            't2_mm' => 'decimal:2',
            'cateto_min_mm' => 'decimal:2',
            'cateto_max_mm' => 'decimal:2',
            'garganta_min_mm' => 'decimal:2',
            'preparacion' => 'array',
            'avisos' => 'array',
            'centro' => 'array',
            'puntos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ModeloMarca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(ModeloMarca::class, 'modelo_marca_id');
    }

    /**
     * Las juntas que se capturaron sobre este cordón, en todas las piezas de
     * la marca.
     *
     * @return HasMany<Junta, $this>
     */
    public function juntas(): HasMany
    {
        return $this->hasMany(Junta::class, 'cordon_id');
    }

    /** Cómo se llama la junta en la captura: S y su número, como en el visor. */
    public function identificador(): string
    {
        return "S{$this->numero}";
    }
}
