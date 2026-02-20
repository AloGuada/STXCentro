<?php

use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Turno;
use App\Models\Infra\TurnoDia;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('infra recorridos index', function () {
    test('index page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/index')
            ->has('fecha')
            ->has('turnoData')
            ->has('estados')
            ->has('estados.compresores')
            ->has('estados.bombas')
            ->has('estados.tanques')
            ->has('estados.ptar')
            ->has('estados.transformadores')
        );
    });

    test('index returns turnoData with configured turnos', function () {
        // Create turno for today's day of week
        $today = now();
        $turno = Turno::factory()->create(['nombre' => 'R1 Matutino', 'activo' => true, 'orden' => 1]);
        TurnoDia::factory()->create([
            'infra_turno_id' => $turno->id,
            'dia_semana' => $today->dayOfWeekIso,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index', ['fecha' => $today->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('turnoData', 1)
            ->where('turnoData.0.turno.nombre', 'R1 Matutino')
        );
    });

    test('index returns virtual turno when no turnos configured', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('turnoData', 1)
            ->where('turnoData.0.turno.nombre', 'Recorrido')
            ->where('turnoData.0.turno.id', null)
        );
    });

    test('index shows existing records for the date', function () {
        Compresor::factory()->create();
        Bomba::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index', ['fecha' => now()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('turnoData.0.compresores.id', fn ($id) => $id > 0)
            ->where('turnoData.0.bombas.id', fn ($id) => $id > 0)
            ->where('turnoData.0.transformador', null)
            ->where('turnoData.0.tanques', null)
            ->where('turnoData.0.ptar', null)
        );
    });

    test('index accepts fecha parameter', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index', ['fecha' => '2026-01-15']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('fecha', '2026-01-15')
        );
    });
});

describe('infra recorridos show', function () {
    test('show page renders compresores', function () {
        Compresor::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'compresores',
                'fecha' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/compresores')
            ->has('data')
            ->has('turno')
        );
    });

    test('show page filters by turno_id', function () {
        $turno = Turno::factory()->create();
        Compresor::factory()->create(['infra_turno_id' => $turno->id]);
        Compresor::factory()->create(['infra_turno_id' => null]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'compresores',
                'fecha' => now()->toDateString(),
                'turno_id' => $turno->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.infra_turno_id', $turno->id)
            ->where('turno.id', $turno->id)
        );
    });

    test('show page renders bombas', function () {
        Bomba::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'bombas',
                'fecha' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/bombas')
        );
    });

    test('show page renders transformadores', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'transformadores',
                'fecha' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/transformadores')
        );
    });

    test('show page renders tanques', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'tanques',
                'fecha' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/tanques')
        );
    });

    test('show page renders ptar', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.show', [
                'sistema' => 'ptar',
                'fecha' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/recorridos/ptar')
        );
    });
});

describe('infra recorridos create', function () {
    test('create page renders compresores form', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.create', ['sistema' => 'compresores']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/sistemas/compresores')
            ->has('turno')
        );
    });

    test('create page renders bombas form', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.create', ['sistema' => 'bombas']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/sistemas/bombas')
        );
    });

    test('create page renders ptar form', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.create', ['sistema' => 'ptar']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/sistemas/ptar')
        );
    });

    test('create page passes turno when turno_id provided', function () {
        $turno = Turno::factory()->create(['nombre' => 'R1 Matutino']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.create', [
                'sistema' => 'compresores',
                'turno_id' => $turno->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('turno.nombre', 'R1 Matutino')
        );
    });
});

describe('infra recorridos store', function () {
    test('compresores can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'compresores']), [
                'compresor_1_status' => true,
                'compresor_1_presion_aire' => 120.50,
                'compresor_1_kwhr' => 150.00,
                'compresor_2_status' => false,
                'compresor_3_status' => true,
                'observaciones' => 'Test observaciones',
            ]);

        $response->assertRedirectContains('/admin/infra/recorridos');
        $this->assertDatabaseHas('infra_compresores', [
            'compresor_1_status' => true,
            'observaciones' => 'Test observaciones',
        ]);
    });

    test('store associates infra_turno_id to the record', function () {
        $turno = Turno::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', [
                'sistema' => 'compresores',
                'turno_id' => $turno->id,
            ]), [
                'compresor_1_status' => true,
                'compresor_2_status' => false,
                'compresor_3_status' => false,
            ]);

        $this->assertDatabaseHas('infra_compresores', [
            'infra_turno_id' => $turno->id,
        ]);
    });

    test('bombas can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'bombas']), [
                'bomba_posos_1' => true,
                'bomba_posos_2' => false,
                'bomba_planta_1' => true,
                'bomba_planta_2' => false,
                'bomba_planta_3' => true,
                'nivel_salmuera' => 75.5,
                'presion_tuberia' => 45.0,
                'bomba_jockey' => false,
                'bomba_electrica' => true,
                'bomba_diesel' => false,
            ]);

        $response->assertRedirectContains('/admin/infra/recorridos');
        $this->assertDatabaseHas('infra_bombas', [
            'bomba_posos_1' => true,
            'nivel_salmuera' => 75.5,
        ]);
    });

    test('transformadores can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'transformadores']), [
                'linea_A' => 250.00,
                'linea_A_max' => 500.00,
                'total_1' => 3500.00,
                'lectura_301' => 120.00,
            ]);

        $response->assertRedirectContains('/admin/infra/recorridos');
        $this->assertDatabaseHas('infra_transformadores', [
            'linea_A' => 250.00,
            'total_1' => 3500.00,
        ]);
    });

    test('tanques can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'tanques']), [
                'pa_sistema_oxigeno' => 150.00,
                'presion_tanque_oxigeno' => 220.00,
                'lt_tanque_oxigeno' => 5000.00,
                'kg_tanque_oxigeno' => 2500.00,
            ]);

        $response->assertRedirectContains('/admin/infra/recorridos');
        $this->assertDatabaseHas('infra_tanques', [
            'pa_sistema_oxigeno' => 150.00,
            'presion_tanque_oxigeno' => 220.00,
        ]);
    });

    test('ptar can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'ptar']), [
                'soplador_activa' => true,
                'bomba_activa' => false,
                'nivel_cloro' => 65.00,
                'trampa_solida' => true,
                'observaciones' => 'PTAR test',
            ]);

        $response->assertRedirectContains('/admin/infra/recorridos');
        $this->assertDatabaseHas('infra_ptar', [
            'soplador_activa' => true,
            'nivel_cloro' => 65.00,
            'observaciones' => 'PTAR test',
        ]);
    });

    test('store assigns current user id', function () {
        $this->actingAs($this->user)
            ->post(route('admin.infra.recorridos.store', ['sistema' => 'ptar']), [
                'soplador_activa' => false,
                'bomba_activa' => false,
                'trampa_solida' => false,
            ]);

        $this->assertDatabaseHas('infra_ptar', [
            'usuario_id' => $this->user->id,
        ]);
    });

    test('legacy records with turno_id null still appear', function () {
        // Legacy record without turno
        Compresor::factory()->create(['infra_turno_id' => null]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.recorridos.index', ['fecha' => now()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('turnoData.0.compresores.id', fn ($id) => $id > 0)
        );
    });
});

describe('infra recorridos auth', function () {
    test('unauthenticated users cannot access recorridos', function () {
        $this->get(route('admin.infra.recorridos.index'))
            ->assertRedirect('/login');
    });

    test('unauthenticated users cannot store recorridos', function () {
        $this->post(route('admin.infra.recorridos.store', ['sistema' => 'ptar']), [
            'soplador_activa' => true,
        ])->assertRedirect('/login');
    });
});
