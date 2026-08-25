<?php

namespace App\Models\Cob;

use App\Models\Obra;
use App\Models\Proyecto;
use App\Services\Cob\IcsoeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comparativo extends Model
{
    use HasFactory;

    protected $table = 'cob_comparativos';

    /**
     * El comparativo define el valor a ejecutar de las obras a precio unitario,
     * así que tocarlo obliga a recalcular el ICSOE del proyecto.
     */
    protected static function booted(): void
    {
        static::saved(function (self $comparativo) {
            if ($comparativo->wasRecentlyCreated || $comparativo->wasChanged(['monto_impacto', 'obra_id'])) {
                app(IcsoeService::class)->programarRecalculo(
                    $comparativo->proyecto_id,
                    'Cambió el comparativo de ingeniería',
                );
            }
        });

        static::deleted(fn (self $comparativo) => app(IcsoeService::class)->programarRecalculo(
            $comparativo->proyecto_id,
            'Se eliminó un comparativo de ingeniería',
        ));
    }

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
