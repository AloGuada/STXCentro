<?php

namespace App\Models;

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Proveedor extends Authenticatable
{
    use HasFactory;

    protected $table = 'proveedores';

    protected string $guard_name = 'proveedor';

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
        'password',
        'contacto_nombre',
        'tiene_acceso_portal',
        'maneja_credito',
        'limite_credito',
        'dias_credito_default',
        'departamento_id',
        'tipo_proveedor',
        'activo',
        'portal_ultimo_acceso',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'portal_ultimo_acceso' => 'datetime',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'proveedor_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'proveedor_id');
    }
}
