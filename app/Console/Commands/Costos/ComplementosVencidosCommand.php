<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Mail\ComplementoPendienteMail;
use App\Models\Costos\ComplementoPago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ComplementosVencidosCommand extends Command
{
    protected $signature = 'costos:complementos-vencidos';

    protected $description = 'Marca como vencidas las obligaciones de complemento de pago cuyo plazo pasó y reenvía recordatorios a los proveedores.';

    public function handle(): int
    {
        $hoy = now()->startOfDay();

        $vencidas = ComplementoPago::query()
            ->where('estatus', ComplementoPagoEstatus::Pendiente->value)
            ->whereDate('fecha_limite', '<', $hoy)
            ->get();

        foreach ($vencidas as $obligacion) {
            $obligacion->transitionTo(ComplementoPagoEstatus::Vencido);
        }

        $this->info("Marcadas {$vencidas->count()} obligacion(es) como vencidas.");

        // Recordatorio periódico a proveedores con obligaciones aún pendientes/vencidas.
        $cada = max(1, (int) config('costos.complemento_pago.recordatorio_cada_dias', 3));
        $recordatorios = 0;

        ComplementoPago::query()
            ->whereIn('estatus', [ComplementoPagoEstatus::Pendiente->value, ComplementoPagoEstatus::Vencido->value])
            ->with('proveedor')
            ->get()
            ->each(function (ComplementoPago $obligacion) use ($hoy, $cada, &$recordatorios) {
                $dias = $obligacion->fecha_generacion->diffInDays($hoy);
                if ($dias > 0 && $dias % $cada === 0 && $obligacion->proveedor?->email) {
                    Mail::to($obligacion->proveedor->email)
                        ->send(new ComplementoPendienteMail($obligacion, $obligacion->proveedor));
                    $recordatorios++;
                }
            });

        $this->info("Enviados {$recordatorios} recordatorio(s).");

        return self::SUCCESS;
    }
}
