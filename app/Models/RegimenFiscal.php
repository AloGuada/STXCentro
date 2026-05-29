<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\RegimenFiscalFactory>
 */
class RegimenFiscal extends Model
{
    use HasFactory;

    protected $table = 'regimenes_fiscales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'descripcion',
        'aplica_persona_fisica',
        'aplica_persona_moral',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aplica_persona_fisica' => 'boolean',
            'aplica_persona_moral' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class);
    }
}
