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
Schedule::command('rh:resolve-cv')->everyFiveMinutes()->withoutOverlapping();
