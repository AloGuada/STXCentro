<?php

namespace App\Models\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un defecto que puede marcar el inspector, tipado por ámbito (soldadura,
 * pintura, las familias de accesorios).
 *
 * Las capturas lo referencian por id: renombrarlo corrige el histórico en
 * lugar de partirlo en dos defectos distintos.
 *
 * @use HasFactory<\Database\Factories\Qal\DefectoFactory>
 */
class Defecto extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_defectos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ambito',
        'nombre',
        'clave',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ambito' => AmbitoDefecto::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDeAmbito(Builder $query, AmbitoDefecto ...$ambitos): void
    {
        $query->whereIn('ambito', array_map(fn (AmbitoDefecto $ambito): string => $ambito->value, $ambitos));
    }
}
