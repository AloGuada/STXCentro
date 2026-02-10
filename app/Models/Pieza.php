<?php

namespace App\Models;

use App\Models\Prod\Fabricado;
use App\Models\Prod\MarcaGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieza extends Model
{
    /** @use HasFactory<\Database\Factories\PiezaFactory> */
    use HasFactory;

    protected $table = 'piezas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'marca',
        'descripcion',
        'longitud',
        'peso',
        'cantidad',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'longitud' => 'decimal:2',
            'cantidad' => 'integer',
            'version' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function marcaGrupos(): HasMany
    {
        return $this->hasMany(MarcaGrupo::class, 'pieza_id');
    }

    public function fabricados(): HasMany
    {
        return $this->hasMany(Fabricado::class, 'pieza_id');
    }
}
