<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('drive:limpiar-archivos')->hourly();

Schedule::command('costos:liberar-apartados-vencidos')->dailyAt('02:00');
Schedule::command('costos:cancelar-requisiciones-vencidas')->dailyAt('03:00');
Schedule::command('costos:cancelar-solicitudes-vencidas')->dailyAt('03:30');
Schedule::command('costos:complementos-vencidos')->dailyAt('04:00');
// Suspendido desde 2026-09-02. Su definición de "sin uso" es de cuando Almacén
// no existía: mira que Compras no lo haya cotizado ni comprado, y no que haya
// existencia. La carga inicial de PIN y SOL dejó 49 productos que cumplen sus
// tres condiciones y guardan medio millón de pesos de pintura en bodega.
//
// No los borraría —las FK de Almacén son restrictOnDelete— pero el borrado es
// un solo DELETE masivo, así que una fila restringida aborta la corrida entera
// y no se limpia nada, ni la basura legítima. Fallaría callado cada domingo.
//
// Se reactiva cuando el comando sepa preguntar por Almacén: hoy sería
// `whereDoesntHave('existencias')`, y después de partir el catálogo,
// `whereDoesntHave('articulo')`, que es la pregunta correcta.
//
// Mientras tanto sigue disponible a mano, y a mano es dry-run salvo --force.
// Schedule::command('costos:limpiar-productos --force')->weeklyOn(0, '05:00');
Schedule::command('rh:resolve-cv')->everyFiveMinutes()->withoutOverlapping();
