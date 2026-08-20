<?php

namespace App\Models\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El informe de pruebas no destructivas que emite el laboratorio.
 *
 * Aquí vive el encabezado una sola vez —RN-19—; la rejilla cuelga en
 * `juntas()`, los parámetros del método en `parametros()`. El folio
 * `reporte_no` es del laboratorio: se teclea, no se genera.
 *
 * PND no se suma con la inspección visual. Esto cuenta juntas soldadas
 * evaluadas por un tercero; `Reporte` cuenta piezas revisadas a la vista. Son
 * universos con denominadores distintos y en el tablero van en bloques
 * separados.
 *
 * @use HasFactory<\Database\Factories\Qal\PndReporteFactory>
 */
class PndReporte extends Model
{
    use HasFactory;

    protected $table = 'qal_pnd_reportes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporte_no',
        'metodo',
        'laboratorio_id',
        'qal_obra_id',
        'lugar',
        'fecha_prueba',
        'fecha_emision',
        'anio',
        'semana',
        'porcentaje_inspeccion',
        'tecnico',
        'material',
        'norma',
        'archivo_pdf',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metodo' => MetodoPnd::class,
            'fecha_prueba' => 'date',
            'fecha_emision' => 'date',
            'anio' => 'integer',
            'semana' => 'integer',
            'porcentaje_inspeccion' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'qal_obra_id');
    }

    /**
     * @return BelongsTo<Laboratorio, $this>
     */
    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(Laboratorio::class, 'laboratorio_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * @return HasMany<PndJunta, $this>
     */
    public function juntas(): HasMany
    {
        return $this->hasMany(PndJunta::class, 'qal_pnd_reporte_id');
    }

    /**
     * @return HasMany<PndParametro, $this>
     */
    public function parametros(): HasMany
    {
        return $this->hasMany(PndParametro::class, 'qal_pnd_reporte_id');
    }

    /**
     * @return HasMany<PndFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(PndFoto::class, 'qal_pnd_reporte_id');
    }
}
