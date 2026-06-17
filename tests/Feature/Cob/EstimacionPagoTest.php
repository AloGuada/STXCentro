<?php

use App\Models\Cob\Estimacion;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
});

describe('admin cob estimacion pagos', function () {
    test('pago can be stored', function () {
        $estimacion = Estimacion::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'estado' => 'facturada',
            'monto_estimado' => 100000.00,
            'monto_pagado' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.pagos.store', [$this->proyecto, $estimacion]), [
                'monto_pagado' => 50000.00,
                'fecha_pago' => '2026-02-15',
                'folio' => 'PAG-001',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_estimaciones_pagos', [
            'estimacion_id' => $estimacion->id,
            'folio' => 'PAG-001',
        ]);
    });

    test('pago updates estimacion monto_pagado', function () {
        $estimacion = Estimacion::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'estado' => 'facturada',
            'monto_estimado' => 100000.00,
            'monto_pagado' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.pagos.store', [$this->proyecto, $estimacion]), [
                'monto_pagado' => 30000.00,
                'fecha_pago' => '2026-02-15',
                'folio' => 'PAG-001',
            ]);

        $estimacion->refresh();
        expect((float) $estimacion->monto_pagado)->toBe(30000.00);
        expect($estimacion->estado)->toBe('pago_parcial');
    });

    test('overpayment is rejected', function () {
        $estimacion = Estimacion::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'estado' => 'facturada',
            'monto_estimado' => 100000.00,
            'monto_pagado' => 90000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.estimaciones.pagos.store', [$this->proyecto, $estimacion]), [
                'monto_pagado' => 20000.00,
                'fecha_pago' => '2026-02-15',
                'folio' => 'PAG-OVER',
            ]);

        $response->assertSessionHasErrors(['monto_pagado']);
        $estimacion->refresh();
        expect((float) $estimacion->monto_pagado)->toBe(90000.00);
    });
});
