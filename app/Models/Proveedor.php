<?php

namespace App\Models;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Enums\ProveedorEstatus;
use App\Models\Costos\ComplementoPago;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

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
        'respetar_fecha_factura',
        'tipo_proveedor',
        'activo',
        'portal_ultimo_acceso',
        'tipo_persona',
        'regimen_fiscal_id',
        'codigo_postal',
        'domicilio_fiscal',
        'domicilio_compra',
        'giro',
        'banco',
        'titular_cuenta',
        'numero_cuenta',
        'clabe',
        'moneda_cuenta',
        'estatus',
        'validado_por',
        'validado_at',
        'observacion_validacion',
        'creado_por',
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
            'respetar_fecha_factura' => 'boolean',
            'activo' => 'boolean',
            'password' => 'hashed',
            'portal_ultimo_acceso' => 'datetime',
            'estatus' => ProveedorEstatus::class,
            'validado_at' => 'datetime',
        ];
    }

    public function regimenFiscal(): BelongsTo
    {
        return $this->belongsTo(RegimenFiscal::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'validado_por');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'proveedor_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'proveedor_id');
    }

    public function complementosPago(): HasMany
    {
        return $this->hasMany(ComplementoPago::class, 'proveedor_id');
    }

    /**
     * Obligaciones de complemento de pago aún no cumplidas (pendientes o
     * vencidas) que bloquean al proveedor.
     *
     * @return array<int, string>
     */
    private static function estatusBloqueantes(): array
    {
        return [ComplementoPagoEstatus::Pendiente->value, ComplementoPagoEstatus::Vencido->value];
    }

    /**
     * True si el proveedor tiene complementos de pago pendientes/vencidos. Un
     * proveedor bloqueado no puede usarse en nuevas requisiciones ni solicitudes
     * de pago hasta regularizar.
     */
    public function bloqueadoPorComplemento(): bool
    {
        if ($this->relationLoaded('complementosPago')) {
            return $this->complementosPago
                ->contains(fn (ComplementoPago $c) => in_array($c->estatus->value, self::estatusBloqueantes(), true));
        }

        return $this->complementosPago()
            ->whereIn('estatus', self::estatusBloqueantes())
            ->exists();
    }

    public function getBloqueadoComplementoAttribute(): bool
    {
        return $this->bloqueadoPorComplemento();
    }

    public function esPersonaFisica(): bool
    {
        return $this->tipo_persona === 'fisica';
    }

    /**
     * Régimen Simplificado de Confianza (RESICO), clave SAT 626.
     */
    public function esResico(): bool
    {
        return $this->regimenFiscal?->clave === '626';
    }

    /**
     * CLABE obligatoria cuando el banco es distinto de Banorte (ahí basta el
     * número de cuenta interno).
     */
    public function requiereClabe(): bool
    {
        return ! self::esBanorte($this->banco);
    }

    public static function esBanorte(?string $banco): bool
    {
        if (! $banco) {
            return false;
        }

        return str_contains(Str::lower(Str::ascii($banco)), 'banorte');
    }

    /**
     * El titular de la cuenta debe corresponder a la razón social del proveedor
     * (evita pagos a terceros). Compara de forma laxa: ignora mayúsculas,
     * acentos, puntuación y sufijos societarios (SA DE CV, S DE RL, etc.).
     */
    public static function titularCoincide(?string $titular, ?string $razonSocial): bool
    {
        return self::normalizarRazon($titular) === self::normalizarRazon($razonSocial)
            && self::normalizarRazon($titular) !== '';
    }

    private static function normalizarRazon(?string $valor): string
    {
        if (! $valor) {
            return '';
        }

        $texto = Str::upper(Str::ascii($valor));
        $texto = preg_replace('/[^A-Z0-9 ]/', ' ', $texto) ?? '';
        $texto = preg_replace('/\b(SA DE CV|S DE RL DE CV|S DE RL|SAPI DE CV|SC|SA|AC)\b/', ' ', $texto) ?? '';

        return trim(preg_replace('/\s+/', '', $texto) ?? '');
    }
}
