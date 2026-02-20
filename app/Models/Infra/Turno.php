<?php

namespace App\Models\Infra;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TurnoFactory> */
    use HasFactory;

    protected $table = 'infra_turnos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'hora_inicio',
        'hora_fin',
        'orden',
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

    public function diasSemana(): HasMany
    {
        return $this->hasMany(TurnoDia::class, 'infra_turno_id');
    }

    /**
     * Retorna turnos activos configurados para el día de la semana de la fecha dada.
     *
     * @return Collection<int, Turno>
     */
    public static function paraFecha(string $fecha): Collection
    {
        $diaSemana = Carbon::parse($fecha)->dayOfWeekIso; // 1=Lun..7=Dom

        return static::query()
            ->where('activo', true)
            ->whereHas('diasSemana', fn ($q) => $q->where('dia_semana', $diaSemana))
            ->with('diasSemana')
            ->orderBy('orden')
            ->get();
    }
}
