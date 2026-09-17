<?php

namespace App\Models\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Obra as ObraDelPortal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El plan de una semana: lo que producción piensa fabricar (2ª) o pintar (3ª)
 * en una obra.
 *
 * Es lo único del avance de producción que se escribe; todo lo demás sale de
 * las inspecciones. Va uno por obra, semana y transformación.
 *
 * @use HasFactory<\Database\Factories\Qal\ProgramacionFactory>
 */
class Programacion extends Model
{
    use HasFactory;

    protected $table = 'qal_programaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'fase',
        'anio',
        'semana',
        'notas',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fase' => FaseTransformacion::class,
            'anio' => 'integer',
            'semana' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ObraDelPortal, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(ObraDelPortal::class, 'obra_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * Las marcas del plan y las bajas, en la misma lista.
     *
     * @return HasMany<ProgramacionMarca, $this>
     */
    public function marcas(): HasMany
    {
        return $this->hasMany(ProgramacionMarca::class, 'programacion_id');
    }

    /** La semana como la escribe la pantalla: `2026-S37`. */
    public function clave(): string
    {
        return sprintf('%04d-S%02d', $this->anio, $this->semana);
    }
}
