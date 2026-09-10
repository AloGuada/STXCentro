<?php

namespace App\Models\Alm;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un programa de inventario: el lote de hojas que reparte un almacén en días.
 *
 * Guarda lo que se decidió en el modal (desde cuándo, qué días, cuántos por
 * día) y lo que el sistema calculó con eso: cuántos artículos entraron y en
 * qué fecha se termina. Las hojas son lo que se cuenta; el programa sólo
 * explica por qué existen.
 *
 * @use HasFactory<\Database\Factories\Alm\ConteoProgramaFactory>
 */
class ConteoPrograma extends Model
{
    use HasFactory;

    protected $table = 'alm_conteo_programas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'almacen_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_semana',
        'articulos_por_dia',
        'articulos_programados',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'dias_semana' => 'array',
            'articulos_por_dia' => 'integer',
            'articulos_programados' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return HasMany<Conteo, $this>
     */
    public function conteos(): HasMany
    {
        return $this->hasMany(Conteo::class, 'programa_id')->orderBy('fecha_programada');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
