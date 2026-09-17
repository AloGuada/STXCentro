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

/**
 * Convierte el IFC de un modelo con el servicio `ifc-service`.
 *
 * La conversión tarda, así que el job no se queda esperando: sube el archivo,
 * se vuelve a encolar y pregunta cada tanto. En cada vuelta se trae las marcas
 * que el servicio ya escribió —su .glb, su plantilla y sus cordones— y las
 * guarda, así que un modelo a medio convertir ya tiene marcas que sirven para
 * el visor. Al terminar baja el modelo entero y cierra.
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

            $indice = $this->traerMarcas($modelo, $cliente, $importador);

            if ($estado['estado'] !== 'listo' || ! ($indice['completo'] ?? false)) {
                $modelo->update([
                    'estatus' => EstatusModelo::Procesando,
                    'resumen' => ['progreso' => ($estado['progreso'] ?? []) + ['marcas_guardadas' => $modelo->marcas()->count()]],
                ]);
                $this->release($this->espera());

                return;
            }

            $this->cerrar($modelo, $indice, $cliente, $importador);
        } catch (Throwable $error) {
            $modelo->update([
                'estatus' => EstatusModelo::Error,
                'error' => mb_substr($error->getMessage(), 0, 2000),
            ]);
        }
    }

    /**
     * Las marcas que el servicio ya escribió y aquí todavía no están: se bajan
     * su .glb y su plantilla y se guardan con sus cordones.
     *
     * @return array<string, mixed> el index.json tal como va
     */
    private function traerMarcas(Modelo $modelo, IfcClient $cliente, ImportadorDeModelo $importador): array
    {
        $indice = $cliente->marcas($modelo->trabajo_externo_id);
        $guardadas = $modelo->marcas()->pluck('marca')->flip();
        $carpeta = "{$modelo->carpeta()}/marks";
        Storage::disk('public')->makeDirectory($carpeta);

        foreach ($indice['marcas'] ?? [] as $marca => $entrada) {
            if ($guardadas->has($marca)) {
                continue;
            }

            foreach (['glb', 'json'] as $extension) {
                $cliente->descargarMarca(
                    $modelo->trabajo_externo_id,
                    "{$entrada['file']}.{$extension}",
                    Storage::disk('public')->path("{$carpeta}/{$entrada['file']}.{$extension}"),
                );
            }

            $importador->importarMarca($modelo, (string) $marca, $entrada, Storage::disk('public')->path("{$carpeta}/{$entrada['file']}.json"));
        }

        return $indice;
    }

    /**
     * @param  array<string, mixed>  $indice
     */
    private function cerrar(Modelo $modelo, array $indice, IfcClient $cliente, ImportadorDeModelo $importador): void
    {
        if (! empty($indice['modelo'])) {
            $cliente->descargarModelo(
                $modelo->trabajo_externo_id,
                Storage::disk('public')->path("{$modelo->carpeta()}/{$indice['modelo']}"),
            );
        }

        $importador->cerrar($modelo, $indice);
        $cliente->borrar($modelo->trabajo_externo_id);
    }

    private function espera(): int
    {
        return max(1, (int) config('services.ifc.poll_segundos'));
    }
}
