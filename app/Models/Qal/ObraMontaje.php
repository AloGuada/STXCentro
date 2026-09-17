<?php

namespace App\Models\Qal;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El avance de montaje de una obra en una semana. Es el **denominador**.
 *
 * Vive solo, uno por obra y semana, en vez de repetirse en cada incidencia como
 * hacía el Excel. Ahí una semana con tres incidencias necesitaba tres filas y
 * las piezas montadas se escribían en la primera con ceros en las demás; en
 * cuanto la cifra caía en la fila equivocada el porcentaje se disparaba.
 *
 * `pz_montadas` nulo es «falta el dato»; cero es «no se montó nada esta
 * semana». La pantalla los pinta distinto porque el porcentaje de la segunda es
 * calculable y el de la primera no.
 *
 * @use HasFactory<\Database\Factories\Qal\ObraMontajeFactory>
 */
class ObraMontaje extends Model
{
    use HasFactory;

    protected $table = 'qal_obra_montaje';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_obra_id',
        'anio',
        'semana',
        'pz_montadas',
        'sin_incidencias',
        'notas',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'semana' => 'integer',
            'pz_montadas' => 'integer',
            'sin_incidencias' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'qal_obra_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }
}
