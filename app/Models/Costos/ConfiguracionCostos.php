<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración (fila única) del módulo de Costos, editable desde el admin:
 * días de apartado de presupuesto, plazos de cancelación automática de
 * requisiciones y solicitudes de pago no aprobadas, y el corte semanal que
 * determina desde qué viernes puede solicitarse el pago.
 */
class ConfiguracionCostos extends Model
{
    protected $table = 'costos_configuracion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dias_apartado',
        'dias_cancelar_requisicion',
        'dias_cancelar_solicitud',
        'corte_activo',
        'corte_dia',
        'corte_hora',
        'gerente_compras_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias_apartado' => 'integer',
            'dias_cancelar_requisicion' => 'integer',
            'dias_cancelar_solicitud' => 'integer',
            'corte_activo' => 'boolean',
            'corte_dia' => 'integer',
        ];
    }

    /**
     * Gerente de compras que firma (única aprobación) las solicitudes de pago
     * generadas desde una orden de compra de contado.
     */
    public function gerenteCompras(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'gerente_compras_id');
    }

    /**
     * Devuelve la fila única de configuración, creándola con los valores por
     * defecto si aún no existe.
     */
    public static function actual(): self
    {
        return static::firstOrCreate([], [
            'dias_apartado' => 5,
            'dias_cancelar_requisicion' => 10,
            'dias_cancelar_solicitud' => 10,
            'corte_activo' => true,
            'corte_dia' => CarbonInterface::WEDNESDAY,
            'corte_hora' => '13:00',
        ]);
    }

    /**
     * Primer viernes que puede seleccionarse como fecha de pago solicitada.
     *
     * Con el corte activo, el viernes de la semana en curso queda bloqueado una
     * vez rebasado el momento de corte (por defecto miércoles 1:00 PM). Sin
     * corte, siempre puede elegirse el viernes de la semana en curso.
     */
    public function minViernes(?CarbonInterface $ahora = null): CarbonImmutable
    {
        $ahora = $ahora ? $ahora->toImmutable() : CarbonImmutable::now();

        $viernes = $ahora->startOfDay();
        while ($viernes->dayOfWeek !== CarbonInterface::FRIDAY) {
            $viernes = $viernes->addDay();
        }

        if ($this->corte_activo && $ahora->greaterThanOrEqualTo($this->momentoCorte($viernes))) {
            $viernes = $viernes->addWeek();
        }

        return $viernes;
    }

    /**
     * Viernes inmediato desde la fecha dada (o desde hoy): devuelve la misma
     * fecha si ya es viernes, o el siguiente viernes. No considera el corte —
     * es el "próximo viernes" a secas, usado para recorrer la fecha de pago de
     * las solicitudes de OC que quedaron vencidas.
     */
    public function proximoViernes(?CarbonInterface $desde = null): CarbonImmutable
    {
        $fecha = $desde ? $desde->toImmutable()->startOfDay() : CarbonImmutable::now()->startOfDay();

        while ($fecha->dayOfWeek !== CarbonInterface::FRIDAY) {
            $fecha = $fecha->addDay();
        }

        return $fecha;
    }

    /**
     * Momento de corte que precede al viernes dado, según el día y hora de
     * corte configurados.
     */
    private function momentoCorte(CarbonImmutable $viernes): CarbonImmutable
    {
        $diasAntes = (CarbonInterface::FRIDAY - $this->corte_dia + 7) % 7;

        return $viernes->subDays($diasAntes)->setTimeFromTimeString($this->corte_hora);
    }

    /**
     * Valida que la fecha sea un viernes no anterior al primer viernes
     * seleccionable según el corte.
     */
    public function fechaPagoValida(CarbonInterface $fecha, ?CarbonInterface $ahora = null): bool
    {
        return $fecha->dayOfWeek === CarbonInterface::FRIDAY
            && $fecha->startOfDay()->greaterThanOrEqualTo($this->minViernes($ahora));
    }
}
