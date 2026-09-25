<?php

use App\Enums\Qal\FaseTransformacion;
use App\Models\Obra;
use App\Models\Prod\Pieza;
use App\Models\Qal\ConfiguracionQal;
use App\Services\Qal\PiezasHabilitadas;
use Database\Seeders\QalAvanceDevSeeder;

/**
 * La obra de prueba del filtro por avance: se puede correr dos veces y deja
 * cada caso como lo promete su docblock.
 */
test('siembra la obra de prueba con cada caso del filtro y se puede repetir', function () {
    $this->seed(QalAvanceDevSeeder::class);
    $this->seed(QalAvanceDevSeeder::class);

    $obraId = Obra::query()->where('no', 'PRUEBA-AVANCE')->value('id');
    $habilitadas = app(PiezasHabilitadas::class);
    $segunda = fn (string $qr): ?string => $habilitadas->motivoDeBloqueo($obraId, FaseTransformacion::Segunda, $qr);

    expect(ConfiguracionQal::actual()->formularios_segun_avance)->toBeTrue()
        ->and(Pieza::query()->where('qr', 'like', 'PA-%')->count())->toBe(20)
        ->and($segunda('PA-CM-101-1'))->toBeNull()
        ->and($segunda('PA-CM-101-4'))->not->toBeNull()
        ->and($segunda('PA-VG-201-2'))->toBeNull()
        ->and($segunda('PA-TR-301-1'))->toBeNull()
        ->and($segunda('PA-PL-401-1'))->not->toBeNull()
        ->and($habilitadas->estado($obraId)['fases'])->toBe(['2ª' => true, '3ª' => false]);
});
