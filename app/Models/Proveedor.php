<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'razon_social',
        'nombre_comercial',
        'rfc',
        'direccion',
        'telefono',
        'email',
        'contacto_nombre',
        'tiene_acceso_portal',
        'maneja_credito',
        'limite_credito',
        'dias_credito_default',
        'departamento_id',
        'tipo_proveedor',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiene_acceso_portal' => 'boolean',
            'maneja_credito' => 'boolean',
            'limite_credito' => 'decimal:2',
            'dias_credito_default' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }
}
