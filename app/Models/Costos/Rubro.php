<?php

namespace App\Models\Costos;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\RubroFactory>
 */
class Rubro extends Model
{
    use HasFactory;

    protected $table = 'costos_rubros';

    protected static function booted(): void
    {
        static::created(function (Rubro $rubro) {
            $obraIds = \App\Models\Obra::query()
                ->where('es_planta', $rubro->ambito === 'planta')
                ->pluck('id');

            $records = $obraIds->map(fn ($obraId) => [
                'obra_id' => $obraId,
                'rubro_id' => $rubro->id,
                'presupuestado' => 0,
                'acumulado' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($records) {
                ObraRubro::insert($records);
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'descripcion',
        'ambito',
        'tipo_rubro_id',
        'departamento_id',
    ];

    public function tipoRubro(): BelongsTo
    {
        return $this->belongsTo(TipoRubro::class, 'tipo_rubro_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }
}
