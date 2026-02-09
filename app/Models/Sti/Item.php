<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\ItemFactory> */
    use HasFactory;

    protected $table = 'sti_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'tipo_id',
        'costo',
        'no_serie',
        'estado',
    ];

    public const ESTADO_LABELS = [
        'disponible' => 'Disponible',
        'instalado' => 'Instalado',
        'dañado' => 'Dañado',
        'baja' => 'Baja',
    ];

    public const ESTADO_COLORS = [
        'disponible' => 'badge-success',
        'instalado' => 'badge-info',
        'dañado' => 'badge-warning',
        'baja' => 'badge-error',
    ];

    public const ACCION_LABELS = [
        'recepcion' => 'Recepción',
        'instalacion' => 'Instalación',
        'retiro' => 'Retiro',
        'baja' => 'Baja',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ItemTipo::class, 'tipo_id');
    }

    public function grupo(): HasOne
    {
        return $this->hasOne(Grupo::class, 'item_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ItemHistorial::class, 'item_id');
    }
}
