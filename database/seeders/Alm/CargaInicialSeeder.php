<?php

namespace Database\Seeders\Alm;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Carga inicial de un almacén: el layout que entregó el área, guardado junto al
 * seeder para que quede en el repo con qué se arrancó ese inventario.
 *
 * Cada almacén tiene su propia subclase —una por archivo— y todas delegan en
 * `alm:importar-insumos`, que es donde vive la validación: unidades y áreas
 * contra el catálogo, descripciones repetidas, y el costo anotado por envase
 * cuando la cantidad se contó a granel. Un seeder que tecleara los renglones
 * dentro sería transcribir a mano lo que ya está en el Excel, y sin nada de eso.
 *
 * Correrlo dos veces duplica el catálogo y duplica el saldo: son artículos
 * nuevos con código nuevo y un ajuste nuevo, no un `firstOrCreate`. Por eso no
 * está en `DatabaseSeeder` — se llama a mano, una vez, cuando el área entrega.
 */
abstract class CargaInicialSeeder extends Seeder
{
    /** Nombre del archivo dentro de `database/seeders/data/almacen`. */
    abstract protected function archivo(): string;

    /**
     * Si el área contó a granel y cotizó por envase. Enciéndelo sólo después de
     * ver el ensayo: divide el costo entre el envase que declara la descripción.
     */
    protected function prorratearEnvase(): bool
    {
        return false;
    }

    public function run(): void
    {
        $ruta = database_path('seeders/data/almacen/'.$this->archivo());

        if (! is_file($ruta)) {
            $this->command?->error("No encuentro el layout: {$ruta}");

            return;
        }

        $autoriza = $this->autorizador();

        if ($autoriza === null) {
            $this->command?->error('No hay ningún usuario que pueda autorizar el ajuste de carga inicial.');

            return;
        }

        Artisan::call('alm:importar-insumos', array_filter([
            'archivo' => [$ruta],
            '--commit' => true,
            '--usuario' => $autoriza->email,
            '--prorratear-envase' => $this->prorratearEnvase(),
        ]), $this->command?->getOutput());
    }

    /**
     * Quién firma el ajuste. En un seeder no hay quién lo teclee, así que se
     * toma al primer super-admin: el ajuste tiene que quedar atribuido a alguien
     * real porque es lo único que explica un saldo sin documento de material
     * detrás.
     */
    private function autorizador(): ?Usuario
    {
        return Usuario::query()->role('super-admin')->orderBy('created_at')->first();
    }
}
