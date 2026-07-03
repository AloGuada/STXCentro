<?php

namespace App\Console\Commands\Folios;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los folios historicos que traen el año a 4 dígitos (20XX) al
 * nuevo formato de 2 dígitos. Es idempotente: los folios ya convertidos (año
 * de 2 dígitos) no coinciden con el patrón antiguo y se ignoran.
 *
 * Por defecto corre en modo simulación (dry-run). Usa --apply para escribir.
 * Nunca sobrescribe: si el folio destino ya existe (colisión), lo salta y lo
 * reporta para que se resuelva manualmente.
 */
class NormalizarAnioFolios extends Command
{
    protected $signature = 'folios:normalizar-anio {--apply : Aplica los cambios (por defecto solo simula)}';

    protected $description = 'Convierte el año de 4 a 2 dígitos en los folios historicos (Costos, Cal, RH)';

    /**
     * Tablas de Costos con folio mensual {PREFIJO}-YYYYMM##. El prefijo ya
     * viene en el propio folio, así que un solo mapper las cubre a todas.
     *
     * @var list<string>
     */
    private array $tablasCostos = [
        'costos_ordenes_compra',
        'costos_facturas',
        'costos_pagos',
        'costos_solicitudes_pago',
        'costos_anticipos',
        'costos_afectaciones_presupuestales',
        'costos_requisiciones',
        'costos_notas_credito',
        'costos_devoluciones',
        'costos_complementos_pago',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');

        $this->info($aplicar
            ? 'Normalizando folios (modo ESCRITURA).'
            : 'Simulación (dry-run). Usa --apply para escribir los cambios.');
        $this->newLine();

        $totalConvertidos = 0;
        $totalColisiones = 0;

        // Costos: {PREFIJO}-YYYYMM## -> {PREFIJO}-YYMM##
        $mapperCostos = fn (string $folio): ?string => preg_match('/^([A-Z]+)-20(\d{2})(\d{2})(\d{2,})$/', $folio, $m)
            ? "{$m[1]}-{$m[2]}{$m[3]}{$m[4]}"
            : null;

        foreach ($this->tablasCostos as $tabla) {
            [$conv, $col] = $this->procesar($tabla, $mapperCostos, $aplicar);
            $totalConvertidos += $conv;
            $totalColisiones += $col;
        }

        // Cal: IVYYYYMM## -> IVYYMM## (solo reportes con folio, no plantillas)
        $mapperCal = fn (string $folio): ?string => preg_match('/^IV20(\d{2})(\d{2})(\d{2,})$/', $folio, $m)
            ? "IV{$m[1]}{$m[2]}{$m[3]}"
            : null;

        [$conv, $col] = $this->procesar('cal_reportes', $mapperCal, $aplicar, fn ($q) => $q->whereNotNull('folio')->where('es_plantilla', false));
        $totalConvertidos += $conv;
        $totalColisiones += $col;

        // RH: {REQ|PA}-YYYY-#### -> {REQ|PA}-YY-####
        $mapperRh = fn (string $folio): ?string => preg_match('/^(REQ|PA)-20(\d{2})-(\d+)$/', $folio, $m)
            ? "{$m[1]}-{$m[2]}-{$m[3]}"
            : null;

        foreach (['rh_requisiciones', 'rh_permisos_ausencia'] as $tabla) {
            [$conv, $col] = $this->procesar($tabla, $mapperRh, $aplicar);
            $totalConvertidos += $conv;
            $totalColisiones += $col;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d folio(s)%s.%s',
            $aplicar ? 'Convertidos' : 'Se convertirían',
            $totalConvertidos,
            $totalColisiones > 0 ? sprintf(', %d colisión(es) omitida(s)', $totalColisiones) : '',
            $aplicar ? '' : ' Ejecuta con --apply para escribir.',
        ));

        return $totalColisiones > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  callable(string): ?string  $mapper
     * @param  (callable(\Illuminate\Database\Query\Builder): \Illuminate\Database\Query\Builder)|null  $filtro
     * @return array{0: int, 1: int} [convertidos, colisiones]
     */
    private function procesar(string $tabla, callable $mapper, bool $aplicar, ?callable $filtro = null): array
    {
        $query = DB::table($tabla)->select('id', 'folio');
        if ($filtro) {
            $filtro($query);
        }

        $filas = $query->get();

        /** @var array<string, int> $existentes */
        $existentes = array_flip(
            DB::table($tabla)->whereNotNull('folio')->pluck('folio')->all()
        );

        /** @var Collection<int, array{id: int, viejo: string, nuevo: string}> $conversiones */
        $conversiones = collect();
        $colisiones = collect();

        foreach ($filas as $fila) {
            if ($fila->folio === null) {
                continue;
            }

            $nuevo = $mapper($fila->folio);
            if ($nuevo === null || $nuevo === $fila->folio) {
                continue;
            }

            if (isset($existentes[$nuevo])) {
                $colisiones->push(['viejo' => $fila->folio, 'nuevo' => $nuevo]);

                continue;
            }

            $conversiones->push(['id' => $fila->id, 'viejo' => $fila->folio, 'nuevo' => $nuevo]);
        }

        if ($conversiones->isEmpty() && $colisiones->isEmpty()) {
            return [0, 0];
        }

        $this->line(sprintf(
            '  <info>%s</info>: %d por convertir%s',
            $tabla,
            $conversiones->count(),
            $colisiones->isNotEmpty() ? sprintf(', <fg=red>%d colisión(es)</>', $colisiones->count()) : '',
        ));

        foreach ($conversiones->take(5) as $c) {
            $this->line("      {$c['viejo']}  ->  {$c['nuevo']}");
        }
        if ($conversiones->count() > 5) {
            $this->line(sprintf('      ... y %d más', $conversiones->count() - 5));
        }

        foreach ($colisiones as $c) {
            $this->line("      <fg=red>COLISIÓN</> {$c['viejo']} -> {$c['nuevo']} (el destino ya existe, se omite)");
        }

        if ($aplicar && $conversiones->isNotEmpty()) {
            DB::transaction(function () use ($tabla, $conversiones): void {
                foreach ($conversiones as $c) {
                    DB::table($tabla)->where('id', $c['id'])->update(['folio' => $c['nuevo']]);
                }
            });
        }

        return [$conversiones->count(), $colisiones->count()];
    }
}
