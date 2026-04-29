<?php

namespace App\Models\Cal;

use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'cal_reportes';

    /**
     * Auto-genera el folio al crear si viene null. El folio es la fuente
     * de verdad persistida; el PDF lo lee directo (no recalcula). Las
     * plantillas no tienen folio.
     */
    protected static function booted(): void
    {
        static::creating(function (self $reporte) {
            if ($reporte->es_plantilla || ! empty($reporte->folio)) {
                return;
            }

            $reporte->folio = static::siguienteFolio($reporte->created_at ?? now());
        });
    }

    /**
     * Siguiente folio disponible para el mes de la fecha dada. Formato
     * IV{YYYY}{MM}{NN} donde NN es el incremental del mes (01..99+).
     * El folio se busca por max(folio) like 'IVYYYYMM%' para no depender
     * de orden cronologico de created_at (a prueba de inserts retroactivos).
     */
    public static function siguienteFolio(CarbonInterface $fecha): string
    {
        $prefix = sprintf('IV%s%s', $fecha->format('Y'), $fecha->format('m'));

        $ultimoFolio = static::where('folio', 'like', $prefix.'%')
            ->where('es_plantilla', false)
            ->orderByDesc('folio')
            ->value('folio');

        $next = $ultimoFolio ? ((int) substr($ultimoFolio, strlen($prefix))) + 1 : 1;

        return sprintf('%s%02d', $prefix, $next);
    }

    protected $fillable = [
        'plano_id',
        'strumis_id',
        'consecutivo',
        'inspector_id',
        'plantilla',
        'aprobado',
        'rechazado',
        'es_plantilla',
        'linea',
        'modulo',
        'comentario',
        'folio',
        'soldador_id',
    ];

    protected function casts(): array
    {
        return [
            'aprobado' => 'datetime',
            'rechazado' => 'datetime',
            'es_plantilla' => 'boolean',
            'linea' => 'integer',
            'modulo' => 'integer',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PiezaPlano::class, 'plano_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'inspector_id');
    }

    public function soldador(): BelongsTo
    {
        return $this->belongsTo(Soldador::class, 'soldador_id');
    }

    public function flechas(): HasMany
    {
        return $this->hasMany(Flecha::class, 'reporte_id');
    }
}
