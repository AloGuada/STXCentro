<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Obra/proyecto de cotización (tabla propia del módulo; no reutiliza `obras` del mono).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraFactory>
 */
class Obra extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'op',
        'factor_contratista',
        'num_grupos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_contratista' => 'decimal:4',
            'num_grupos' => 'integer',
        ];
    }

    public function generadoras(): HasMany
    {
        return $this->hasMany(Generadora::class, 'obra_id');
    }

    public function tarjetas(): HasMany
    {
        return $this->hasMany(Tarjeta::class, 'obra_id');
    }

    public function insumoOverrides(): HasMany
    {
        return $this->hasMany(ObraInsumoOverride::class, 'obra_id');
    }

    public function factorOverrides(): HasMany
    {
        return $this->hasMany(ObraFactorOverride::class, 'obra_id');
    }

    public function seccionesMontaje(): HasMany
    {
        return $this->hasMany(SeccionMontaje::class, 'obra_id');
    }

    public function cuadrillaGlobal(): HasMany
    {
        return $this->hasMany(ObraCuadrillaGlobal::class, 'obra_id');
    }

    public function fletesEstandar(): HasMany
    {
        return $this->hasMany(ObraFleteEstandar::class, 'obra_id');
    }

    public function fletesViaticos(): HasMany
    {
        return $this->hasMany(ObraFleteViatico::class, 'obra_id');
    }

    public function resumenColumnas(): HasMany
    {
        return $this->hasMany(ResumenColumna::class, 'obra_id');
    }

    public function resumenCoeficientes(): HasMany
    {
        return $this->hasMany(ObraResumenCoeficiente::class, 'obra_id');
    }

    public function resumenCeldaOverrides(): HasMany
    {
        return $this->hasMany(ObraResumenCeldaOverride::class, 'obra_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ObraVersion::class, 'obra_id');
    }
}
