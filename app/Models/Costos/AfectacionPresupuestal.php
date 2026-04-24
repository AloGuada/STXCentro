<?php

namespace App\Models\Costos;

use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @use HasFactory<\Database\Factories\Costos\AfectacionPresupuestalFactory>
 */
class AfectacionPresupuestal extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected $table = 'costos_afectaciones_presupuestales';

    protected static string $folioPrefix = 'AF';

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
