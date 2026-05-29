<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\UsoCfdiFactory>
 */
class UsoCfdi extends Model
{
    use HasFactory;

    protected $table = 'costos_usos_cfdi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function requisicionDetalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class, 'uso_cfdi_id');
    }
}
