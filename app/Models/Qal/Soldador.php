<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soldador del padrón.
 *
 * La `clave` es la que se estampa en la pieza y la que enlaza al soldador con su
 * WPQR en el dosier: si no coincide, el dosier reporta que no tiene certificado.
 */
class Soldador extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_soldadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'clave',
        'certificacion',
        'certificacion_vence_at',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'certificacion_vence_at' => 'date',
        ];
    }

    /**
     * Sin certificación vigente no debería firmarse una junta suya. Sin fecha
     * capturada no se puede afirmar nada, así que no se da por vencida.
     */
    public function certificacionVencida(): bool
    {
        return $this->certificacion_vence_at !== null
            && $this->certificacion_vence_at->isPast();
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'soldador_id');
    }
}
