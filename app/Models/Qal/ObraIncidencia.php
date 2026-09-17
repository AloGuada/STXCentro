<?php

namespace App\Models\Qal;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una incidencia aparecida durante el montaje, con su responsable.
 *
 * El **numerador** son las piezas con defecto, no el número de incidencias: un
 * solo hallazgo puede afectar a diez piezas y lo que se publica es cuánto de lo
 * montado dio problema.
 *
 * El estado no tiene columna propia. Sale de `cerrada_en`, que hace falta de
 * todas formas para saber cuánto tardó en resolverse; guardar además un texto
 * «Abierta»/«Cerrada» sería la misma verdad escrita dos veces, y tarde o
 * temprano dejan de coincidir.
 *
 * @use HasFactory<\Database\Factories\Qal\ObraIncidenciaFactory>
 */
class ObraIncidencia extends Model
{
    use HasFactory;

    protected $table = 'qal_obra_incidencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_obra_id',
        'anio',
        'semana',
        'fecha',
        'area',
        'departamento',
        'pz_defecto',
        'folio',
        'descripcion',
        'cerrada_en',
        'capturista_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['abierta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'semana' => 'integer',
            'fecha' => 'date',
            'area' => AreaIncidencia::class,
            'departamento' => DepartamentoIncidencia::class,
            'pz_defecto' => 'integer',
            'cerrada_en' => 'datetime',
        ];
    }

    /** Sin fecha de cierre, la incidencia sigue viva. */
    public function getAbiertaAttribute(): bool
    {
        return $this->cerrada_en === null;
    }

    public function scopeAbiertas(Builder $consulta): Builder
    {
        return $consulta->whereNull('cerrada_en');
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'qal_obra_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }
}
