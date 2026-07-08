<?php

namespace App\Models\Costos;

use App\Enums\Costos\PresupuestoEstatus;
use App\Models\Concerns\HasStateMachine;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Presupuesto de costos ligado polimórficamente a un Proyecto, Obra o Partida.
 * Concentra el nombre interno y el estatus (Activo/Cerrado) que sustituye al
 * estado de la obra de cobranza para bloquear el gasto. Posee los renglones
 * (ObraRubro) vía presupuesto_id.
 *
 * @use HasFactory<\Database\Factories\Costos\PresupuestoFactory>
 */
class Presupuesto extends Model
{
    use HasFactory, HasStateMachine;

    protected $table = 'costos_presupuestos';

    protected static string $stateEnum = PresupuestoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'presupuestable_type',
        'presupuestable_id',
        'nombre_interno',
        'op_interno',
        'estatus',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => PresupuestoEstatus::class,
        ];
    }

    public function presupuestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function rubros(): HasMany
    {
        return $this->hasMany(ObraRubro::class, 'presupuesto_id');
    }

    /**
     * Nombre a mostrar: el interno si existe, si no el número/descripción que
     * viene del presupuestable (cobranza).
     */
    public function nombreMostrar(): string
    {
        if (filled($this->nombre_interno)) {
            return $this->nombre_interno;
        }

        $presupuestable = $this->presupuestable;

        return $presupuestable->no ?? $presupuestable->descripcion ?? '—';
    }

    public function getNombreMostrarAttribute(): string
    {
        return $this->nombreMostrar();
    }

    /**
     * Descripción a mostrar: el nombre interno si existe, si no la descripción
     * que viene del presupuestable (cobranza).
     */
    public function descripcionMostrar(): ?string
    {
        if (filled($this->nombre_interno)) {
            return $this->nombre_interno;
        }

        return $this->presupuestable->descripcion ?? null;
    }

    public function getDescripcionMostrarAttribute(): ?string
    {
        return $this->descripcionMostrar();
    }

    /**
     * OP a mostrar: la interna si existe, si no el número (OP) que viene del
     * presupuestable (cobranza). Puede ser null (ej. partidas sin número).
     */
    public function opMostrar(): ?string
    {
        if (filled($this->op_interno)) {
            return $this->op_interno;
        }

        return $this->presupuestable->no ?? null;
    }

    public function getOpMostrarAttribute(): ?string
    {
        return $this->opMostrar();
    }

    /**
     * El presupuesto está cerrado para efectos de gasto según su propio estatus
     * (independiente del estado del presupuestable en cobranza).
     */
    public function estaCerrado(): bool
    {
        return $this->estatus === PresupuestoEstatus::Cerrado;
    }

    /**
     * Ámbito de rubros aplicable: 'planta' solo si el presupuestable es una obra
     * de planta; en cualquier otro caso (proyecto, obra, partida) es 'obra'.
     */
    public function ambitoRubros(): string
    {
        $presupuestable = $this->presupuestable;

        return ($presupuestable instanceof Obra && $presupuestable->es_planta) ? 'planta' : 'obra';
    }

    /**
     * Crea un renglón de presupuesto para un rubro, sincronizando obra_id
     * mientras la columna de compatibilidad exista.
     */
    public function crearRubro(int $rubroId, float $presupuestado = 0): ObraRubro
    {
        return $this->rubros()->create([
            'obra_id' => $this->obraIdCompat(),
            'rubro_id' => $rubroId,
            'presupuestado' => $presupuestado,
        ]);
    }

    /**
     * Siembra todos los rubros del ámbito que aún no están asignados a este
     * presupuesto (el botón "agregar todos los centros de costo").
     */
    public function sembrarRubrosFaltantes(): void
    {
        $asignados = $this->rubros()->pluck('rubro_id');

        $rubros = Rubro::query()
            ->where('ambito', $this->ambitoRubros())
            ->whereNotIn('id', $asignados)
            ->get();

        foreach ($rubros as $rubro) {
            $this->crearRubro($rubro->id);
        }
    }

    private function obraIdCompat(): ?int
    {
        return $this->presupuestable_type === Obra::class ? $this->presupuestable_id : null;
    }
}
