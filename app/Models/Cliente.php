<?php

namespace App\Models;

use App\Models\Cob\Contacto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'rfc',
        'direccion',
        'telefono',
        'email',
        'activo',
        'contacto_principal_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function contactoPrincipal(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_principal_id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class, 'cliente_id');
    }

    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class, 'cliente_id');
    }
}
