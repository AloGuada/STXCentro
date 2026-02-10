<?php

namespace App\Models\Prod;

use App\Models\Pieza;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fabricado extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\FabricadoFactory> */
    use HasFactory;

    protected $table = 'prod_fabricados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'destajo_id',
        'dest_grupo_id',
        'pieza_id',
        'cantidad',
        'porcentual',
        'precio_unitario_aplicado',
        'total_calculado',
        'saldo_pendiente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'porcentual' => 'decimal:2',
            'precio_unitario_aplicado' => 'decimal:2',
            'total_calculado' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
        ];
    }

    public function destajo(): BelongsTo
    {
        return $this->belongsTo(Destajo::class, 'destajo_id');
    }

    public function destGrupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'dest_grupo_id');
    }

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }
}
