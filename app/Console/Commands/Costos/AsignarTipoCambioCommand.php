<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\TipoCambio;
use App\Services\Costos\TipoCambioService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Asigna a una solicitud de pago o requisición (por folio) el tipo de cambio
 * del día según su divisa: detecta si el documento usa USD o EUR y consulta la
 * tasa de referencia (Banxico FIX / ECB, cacheada por día). Documentos en MXN
 * no aplican. Si el documento ya tenía un TC capturado (≠ 1), pide confirmación
 * antes de sobrescribirlo.
 */
class AsignarTipoCambioCommand extends Command
{
    protected $signature = 'costos:asignar-tipo-cambio
        {folio? : Folio del documento (REQ-... o SP-...); se pregunta si se omite}
        {--dry-run : Muestra qué haría sin escribir}';

    protected $description = 'Asigna a una solicitud/requisición en divisa (por folio) el tipo de cambio del día (USD → Banxico, EUR → ECB).';

    public function handle(TipoCambioService $tipoCambio): int
    {
        $folio = strtoupper(trim((string) ($this->argument('folio') ?: $this->ask('Folio del documento (REQ-... o SP-...)'))));

        if ($folio === '') {
            $this->error('Debes indicar un folio.');

            return self::INVALID;
        }

        $doc = $this->resolver($folio);

        if (! $doc) {
            $this->error("No se encontró ninguna solicitud ni requisición con folio '{$folio}'.");

            return self::FAILURE;
        }

        $etiqueta = $doc instanceof Requisicion ? 'Requisición' : 'Solicitud de pago';

        if ($error = $this->estadoNoEditable($doc)) {
            $this->error("{$etiqueta} {$folio}: {$error}");

            return self::FAILURE;
        }

        $moneda = $this->monedaDocumento($doc);

        if ($moneda === null) {
            $this->error("{$etiqueta} {$folio}: no se pudo determinar la divisa (sin cotizaciones seleccionadas).");

            return self::FAILURE;
        }

        if ($moneda === 'mixta') {
            $this->error("{$etiqueta} {$folio}: mezcla más de una divisa en sus cotizaciones; captura el TC manualmente.");

            return self::FAILURE;
        }

        if ($moneda === 'mxn') {
            $this->info("{$etiqueta} {$folio} está en MXN; no requiere tipo de cambio.");

            return self::SUCCESS;
        }

        $tasa = $tipoCambio->mxnPorUnidad($moneda);
        $fuente = TipoCambio::query()
            ->whereDate('fecha', now()->toDateString())
            ->where('moneda', $moneda)
            ->value('fuente') ?? 'caché';

        $actual = (float) ($doc->tipo_cambio ?? 1);

        $this->line("{$etiqueta} {$folio} — divisa ".strtoupper($moneda));
        $this->line('TC actual: '.number_format($actual, 6).'  →  TC del día ('.$fuente.'): '.number_format($tasa, 6));

        if ($this->option('dry-run')) {
            $this->comment('Modo dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        if ($actual !== 1.0 && abs($actual - $tasa) > 0.000001) {
            if (! $this->confirm('El documento ya tiene un TC capturado ('.number_format($actual, 6).'). ¿Sobrescribirlo?')) {
                $this->comment('Sin cambios.');

                return self::SUCCESS;
            }
        }

        $doc->update(['tipo_cambio' => $tasa]);

        $this->info("Tipo de cambio {$tasa} asignado a {$etiqueta} {$folio}.");

        if ($this->tieneCargosAplicados($doc)) {
            $flag = $doc instanceof Requisicion ? '--requisicion' : '--solicitud';
            $this->comment("El documento ya ejerció presupuesto. Para re-aplicar el TC a sus cargos corre: php artisan costos:recalcular-tipo-cambio {$flag}={$doc->id}");
        }

        return self::SUCCESS;
    }

    private function resolver(string $folio): Requisicion|SolicitudPago|null
    {
        if (str_starts_with($folio, 'REQ')) {
            return Requisicion::where('folio', $folio)->first();
        }

        if (str_starts_with($folio, 'SP')) {
            return SolicitudPago::where('folio', $folio)->first();
        }

        return SolicitudPago::where('folio', $folio)->first()
            ?? Requisicion::where('folio', $folio)->first();
    }

    /**
     * La divisa del documento: `tipo_moneda` en la solicitud; en la requisición
     * sale de sus cotizaciones seleccionadas (o de todas las cotizaciones si aún
     * no hay selección). 'mixta' si mezcla más de una divisa distinta de MXN.
     */
    private function monedaDocumento(Requisicion|SolicitudPago $doc): ?string
    {
        if ($doc instanceof SolicitudPago) {
            return strtolower((string) ($doc->tipo_moneda ?? 'mxn'));
        }

        $doc->load('detalles.selecciones.cotizacionPrecio', 'detalles.cotizaciones');

        $monedas = $doc->detalles
            ->flatMap->selecciones
            ->map(fn ($s) => strtolower((string) ($s->cotizacionPrecio?->moneda ?? 'mxn')));

        if ($monedas->isEmpty()) {
            $monedas = $doc->detalles
                ->flatMap->cotizaciones
                ->map(fn ($c) => strtolower((string) ($c->moneda ?? 'mxn')));
        }

        if ($monedas->isEmpty()) {
            return null;
        }

        $divisas = $monedas->unique()->reject(fn ($m) => $m === 'mxn')->values();

        return match ($divisas->count()) {
            0 => 'mxn',
            1 => $divisas->first(),
            default => 'mixta',
        };
    }

    private function estadoNoEditable(Requisicion|SolicitudPago $doc): ?string
    {
        if ($doc instanceof Requisicion
            && in_array($doc->estatus, [RequisicionEstatus::Liberada, RequisicionEstatus::Cancelada], true)) {
            return 'ya no admite cambios de tipo de cambio (liberada/cancelada).';
        }

        if ($doc instanceof SolicitudPago
            && in_array($doc->estatus, [SolicitudPagoEstatus::Pagada, SolicitudPagoEstatus::Cancelada], true)) {
            return 'ya no admite cambios de tipo de cambio (pagada/cancelada).';
        }

        return null;
    }

    private function tieneCargosAplicados(Model $doc): bool
    {
        return \App\Models\Costos\RubroAfectado::query()
            ->where('entrada_type', $doc::class)
            ->where('entrada_id', $doc->getKey())
            ->where('estatus', \App\Enums\Costos\RubroAfectadoEstatus::Aplicado->value)
            ->exists();
    }
}
