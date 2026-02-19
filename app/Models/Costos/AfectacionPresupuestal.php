<?php

namespace App\Models\Costos;

use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * @use HasFactory<\Database\Factories\Costos\AfectacionPresupuestalFactory>
 */
class AfectacionPresupuestal extends Model
{
    use HasFactory;

    protected $table = 'costos_afectaciones_presupuestales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'fecha',
        'tipo_origen',
        'descripcion',
        'monto_total',
        'estatus',
        'proveedor_id',
        'departamento_id',
        'creado_por',
        'aprobado_por',
        'fecha_aprobacion',
        'pdf_formato_path',
        'pdf_firmado_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_total' => 'decimal:2',
            'fecha_aprobacion' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $afectacion) {
            if (empty($afectacion->folio)) {
                $prefix = sprintf('AF-%s%s', now()->format('Y'), now()->format('m'));
                $last = DB::table('costos_afectaciones_presupuestales')
                    ->where('folio', 'like', "{$prefix}%")
                    ->max('folio');

                $next = $last
                    ? ((int) substr($last, -2)) + 1
                    : 1;

                $afectacion->folio = sprintf('%s%02d', $prefix, $next);
            }
        });
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AfectacionDetalle::class, 'afectacion_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(AfectacionHistorial::class, 'afectacion_id');
    }

    public function rubrosAfectados(): MorphMany
    {
        return $this->morphMany(RubroAfectado::class, 'entrada');
    }
}
