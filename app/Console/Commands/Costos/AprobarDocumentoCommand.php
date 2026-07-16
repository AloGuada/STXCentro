<?php

namespace App\Console\Commands\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\AprobacionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aprueba una solicitud de pago o requisición por folio o id, firmando su
 * cadena de aprobación nivel por nivel. Permite saltar niveles (ej. Director)
 * cancelando sus firmas pendientes para que el documento se complete sin ellos.
 */
class AprobarDocumentoCommand extends Command
{
    protected $signature = 'costos:aprobar
        {identificador : Folio o id de la solicitud/requisición}
        {--tipo= : solicitud|requisicion (para desambiguar cuando el id existe en ambas)}
        {--saltar-nivel=* : Nivel(es) de aprobación a saltar (ej. 4 = Director); sus firmas pendientes se cancelan}
        {--motivo= : Observación registrada en cada firma}
        {--dry-run : Muestra qué haría sin ejecutar}';

    protected $description = 'Aprueba una solicitud de pago o requisición por folio/id, con opción de saltar niveles (ej. Director).';

    public function handle(AprobacionService $service): int
    {
        $ident = (string) $this->argument('identificador');
        $tipo = $this->option('tipo');
        $saltar = array_map('intval', (array) $this->option('saltar-nivel'));
        $motivo = $this->option('motivo') ?: 'Aprobado por comando';
        $dry = (bool) $this->option('dry-run');

        if ($tipo !== null && ! in_array($tipo, ['solicitud', 'requisicion'], true)) {
            $this->error("--tipo debe ser 'solicitud' o 'requisicion'.");

            return self::INVALID;
        }

        [$doc, $tipoDetectado] = $this->resolver($ident, $tipo);

        if ($tipoDetectado === 'ambiguo') {
            $this->error("El identificador '{$ident}' existe como solicitud Y requisición. Especifica --tipo=solicitud|requisicion.");

            return self::FAILURE;
        }

        if (! $doc) {
            $this->error("No se encontró ningún documento con '{$ident}'".($tipo ? " (tipo {$tipo})" : '').'.');

            return self::FAILURE;
        }

        if (($salida = $this->validarEstatus($doc, $tipoDetectado)) !== null) {
            return $salida;
        }

        $pendientes = $doc->cadenaAprobacion()
            ->where('estatus', AprobacionEstatus::Pendiente->value)
            ->orderBy('nivel')
            ->get();

        if ($pendientes->isEmpty()) {
            $this->warn("El documento {$doc->folio} no tiene aprobaciones pendientes.");

            return self::SUCCESS;
        }

        $aFirmar = $pendientes->reject(fn ($a) => in_array((int) $a->nivel, $saltar, true));

        $this->line("Documento: <info>{$doc->folio}</info> ({$tipoDetectado}) — estatus actual: {$doc->estatus->value}");
        $this->table(
            ['Nivel', 'Aprobador', 'Acción'],
            $pendientes->map(fn ($a) => [
                $a->nivel,
                optional($a->aprobador)->name ?? $a->aprobador_id ?? '—',
                in_array((int) $a->nivel, $saltar, true) ? 'SALTAR (cancelar)' : 'FIRMAR',
            ])->all(),
        );

        if ($dry) {
            $this->comment('DRY-RUN: no se ejecutó nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($doc, $service, $saltar, $aFirmar, $motivo): void {
            // 1) Cancelar los niveles a saltar (ej. Director) para que la cadena
            //    pueda cerrar sin ellos.
            if ($saltar !== []) {
                $doc->cadenaAprobacion()
                    ->where('estatus', AprobacionEstatus::Pendiente->value)
                    ->whereIn('nivel', $saltar)
                    ->update([
                        'estatus' => AprobacionEstatus::Cancelada->value,
                        'fecha_respuesta' => now(),
                        'observaciones' => 'Nivel saltado por comando',
                    ]);
            }

            // 2) Firmar el resto por nivel ascendente. AprobacionService cierra
            //    el nivel (lógica OR) y dispara onAprobacionCompleta cuando ya no
            //    quedan pendientes → apartado→aplicado (solicitud) / aprobada.
            while (true) {
                $siguiente = $doc->cadenaAprobacion()
                    ->where('estatus', AprobacionEstatus::Pendiente->value)
                    ->orderBy('nivel')
                    ->first();

                if (! $siguiente) {
                    break;
                }

                if ($siguiente->aprobador) {
                    Auth::setUser($siguiente->aprobador);
                }

                $service->aprobar($siguiente, $motivo);
            }

            // 3) Si TODO se saltó (no quedó nivel por firmar), completar a mano.
            if ($aFirmar->isEmpty() && $doc instanceof Aprobable) {
                $doc->onAprobacionCompleta(Auth::id());
            }
        });

        $doc->refresh();
        $this->info("Listo. {$doc->folio} quedó en estatus '{$doc->estatus->value}'.");

        if ($tipoDetectado === 'requisicion' && $doc->estatus->value === RequisicionEstatus::Aprobada->value) {
            $this->comment('Nota: la requisición aprobada conserva su APARTADO; el acumulado se consolida (Aplicado) al LIBERAR la requisición (generar OC).');
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve el documento por folio o id.
     *
     * @return array{0: (SolicitudPago|Requisicion)|null, 1: string} [$doc, 'solicitud'|'requisicion'|'ambiguo'|'none']
     */
    private function resolver(string $ident, ?string $tipo): array
    {
        $numerico = ctype_digit($ident);

        $buscarSol = fn (): ?SolicitudPago => $numerico
            ? SolicitudPago::find((int) $ident)
            : SolicitudPago::where('folio', $ident)->first();

        $buscarReq = fn (): ?Requisicion => $numerico
            ? Requisicion::find((int) $ident)
            : Requisicion::where('folio', $ident)->first();

        if ($tipo === 'solicitud') {
            return [$buscarSol(), 'solicitud'];
        }

        if ($tipo === 'requisicion') {
            return [$buscarReq(), 'requisicion'];
        }

        $sol = $buscarSol();
        $req = $buscarReq();

        if ($sol && $req) {
            return [null, 'ambiguo'];
        }

        if ($sol) {
            return [$sol, 'solicitud'];
        }

        if ($req) {
            return [$req, 'requisicion'];
        }

        return [null, 'none'];
    }

    /**
     * Valida que el documento esté en un estado aprobable. Devuelve un código de
     * salida si NO se debe continuar, o null si todo bien.
     */
    private function validarEstatus(Model $doc, string $tipo): ?int
    {
        $estado = $doc->estatus->value;

        if ($tipo === 'solicitud') {
            if (in_array($estado, [SolicitudPagoEstatus::Aprobada->value, SolicitudPagoEstatus::Pagada->value], true)) {
                $this->info("La solicitud {$doc->folio} ya está en '{$estado}'. Nada que hacer.");

                return self::SUCCESS;
            }

            if ($estado !== SolicitudPagoEstatus::PendienteFirma->value) {
                $this->error("La solicitud {$doc->folio} está en '{$estado}'. Debe estar en 'pendiente_firma' (enviada a aprobación) para aprobarla.");

                return self::FAILURE;
            }

            return null;
        }

        if (in_array($estado, [RequisicionEstatus::Aprobada->value, RequisicionEstatus::Liberada->value], true)) {
            $this->info("La requisición {$doc->folio} ya está en '{$estado}'. Nada que hacer.");

            return self::SUCCESS;
        }

        if ($estado !== RequisicionEstatus::PendienteAprobacion->value) {
            $this->error("La requisición {$doc->folio} está en '{$estado}'. Debe estar en 'pendiente_aprobacion' para aprobarla.");

            return self::FAILURE;
        }

        return null;
    }
}
