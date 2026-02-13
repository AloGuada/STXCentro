<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'obras';

    protected static function booted(): void
    {
        static::created(function (Obra $obra) {
            $rubros = Costos\Rubro::pluck('id');

            $obra->obraRubros()->createMany(
                $rubros->map(fn ($rubroId) => [
                    'rubro_id' => $rubroId,
                    'presupuestado' => 0,
                    'acumulado' => 0,
                ])->all()
            );
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'no',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'presupuesto_total',
        'estatus',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'presupuesto_total' => 'decimal:2',
        ];
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class, 'obra_id');
    }

    public function gruposPrecios(): HasMany
    {
        return $this->hasMany(Prod\GrupoPrecio::class, 'obra_id');
    }

    public function obraRubros(): HasMany
    {
        return $this->hasMany(Costos\ObraRubro::class, 'obra_id');
    }
}
