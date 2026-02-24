<?php

namespace App\Models;

use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Registro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concepto extends Model
{
    /** @use HasFactory<\Database\Factories\ConceptoFactory> */
    use HasFactory;

    protected $table = 'conceptos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'marca',
        'descripcion',
        'cantidad',
        'peso_unitario',
        'version',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'peso_unitario' => 'decimal:3',
            'version' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function grupoPrecioConceptos(): HasMany
    {
        return $this->hasMany(GrupoPrecioConcepto::class, 'concepto_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'concepto_id');
    }
}
