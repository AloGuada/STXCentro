<?php

namespace App\Jobs\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Models\Qal\Modelo;
use App\Services\Qal\Ifc\IfcClient;
use App\Services\Qal\Ifc\ImportadorDeModelo;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Convierte el IFC de un modelo con el servicio `ifc-service`.
 *
 * La conversión tarda minutos, así que el job no se queda esperando: sube el
 * archivo, se vuelve a encolar y pregunta cada tanto. Cuando el servicio
 * termina, descarga el zip, lo descomprime en el disco público (el visor pide
 * los .glb directo) y guarda marcas y cordones.
 *
 * Un fallo no se reintenta: deja el modelo en error con el mensaje, y se
 * vuelve a procesar a mano. Reintentar a ciegas sólo repetiría el mismo error
 * del IFC.
 */
class ProcesarModeloIfc implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $modeloId)
    {
        $this->onQueue('ifc');
    }

    /** Cada vuelta que pregunta cuenta como intento: el límite es el tiempo, no las vueltas. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(ImportadorDeModelo $importador): void
    {
        $modelo = Modelo::query()->find($this->modeloId);

        if ($modelo === null || $modelo->estatus->terminado()) {
            return;
        }

        $cliente = IfcClient::fromConfig();

        try {
            if ($modelo->trabajo_externo_id === null) {
                $trabajo = $cliente->enviar(Storage::disk('local')->path($modelo->archivo_ifc), $modelo->nombre_original);
                $modelo->update(['trabajo_externo_id' => $trabajo, 'estatus' => EstatusModelo::Procesando]);
                $this->release($this->espera());

                return;
            }

            $estado = $cliente->estado($modelo->trabajo_externo_id);

            if ($estado['estado'] === 'error') {
                throw new RuntimeException('El servicio no pudo convertir el IFC: '.($estado['error'] ?? 'sin detalle'));
            }

            if ($estado['estado'] !== 'listo') {
                $modelo->update(['estatus' => EstatusModelo::Procesando, 'resumen' => ['progreso' => $estado['progreso'] ?? null]]);
                $this->release($this->espera());

                return;
            }

            $this->importar($modelo, $cliente, $importador);
        } catch (Throwable $error) {
            $modelo->update([
                'estatus' => EstatusModelo::Error,
                'error' => mb_substr($error->getMessage(), 0, 2000),
            ]);
        }
    }

    private function importar(Modelo $modelo, IfcClient $cliente, ImportadorDeModelo $importador): void
    {
        $zip = "{$modelo->carpeta()}/resultado.zip";
        Storage::disk('local')->makeDirectory($modelo->carpeta());
        $cliente->descargarResultado($modelo->trabajo_externo_id, Storage::disk('local')->path($zip));

        Storage::disk('public')->deleteDirectory($modelo->carpeta());
        Storage::disk('public')->makeDirectory($modelo->carpeta());

        $archivo = new ZipArchive;

        if ($archivo->open(Storage::disk('local')->path($zip)) !== true) {
            throw new RuntimeException('El resultado del servicio no es un zip válido.');
        }

        $archivo->extractTo(Storage::disk('public')->path($modelo->carpeta()));
        $archivo->close();
        Storage::disk('local')->delete($zip);

        $importador->importar($modelo, Storage::disk('public')->path($modelo->carpeta()));
        $cliente->borrar($modelo->trabajo_externo_id);
    }

    private function espera(): int
    {
        return max(1, (int) config('services.ifc.poll_segundos'));
    }
}
