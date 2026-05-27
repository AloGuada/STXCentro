<?php

use App\Models\Rh\Onboarding;
use App\Models\Rh\OnboardingTarea;
use App\Models\Rh\OnboardingTareaPlantilla;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Puesto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.onboarding.ver', 'rh.onboarding.editar', 'rh.onboarding.crear'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.onboarding.ver', 'rh.onboarding.editar', 'rh.onboarding.crear']);
});

describe('admin rh onboarding', function () {
    test('show page can be rendered', function () {
        $onboarding = Onboarding::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.onboarding.show', $onboarding));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/onboarding/show')
            ->has('onboarding')
        );
    });

    test('tarea can be added', function () {
        $onboarding = Onboarding::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.onboarding.tareas.store', $onboarding), [
                'titulo' => 'Configurar email',
                'descripcion' => 'Setup email corporativo',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_onboarding_tareas', [
            'onboarding_id' => $onboarding->id,
            'titulo' => 'Configurar email',
        ]);
    });

    test('tarea can be toggled', function () {
        $onboarding = Onboarding::factory()->create();
        $tarea = OnboardingTarea::factory()->create([
            'onboarding_id' => $onboarding->id,
            'completada' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.onboarding.tareas.toggle', [$onboarding, $tarea]));

        $response->assertRedirect();
        $tarea->refresh();
        expect($tarea->completada)->toBeTrue();
        expect($tarea->fecha_completada)->not->toBeNull();
    });

    test('progreso is recalculated after toggle', function () {
        $onboarding = Onboarding::factory()->create(['progreso' => 0]);
        OnboardingTarea::factory()->create([
            'onboarding_id' => $onboarding->id,
            'completada' => true,
        ]);
        $tarea2 = OnboardingTarea::factory()->create([
            'onboarding_id' => $onboarding->id,
            'completada' => false,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.rh.onboarding.tareas.toggle', [$onboarding, $tarea2]));

        $onboarding->refresh();
        expect($onboarding->progreso)->toBe(100);
    });

    test('tarea can be deleted', function () {
        $onboarding = Onboarding::factory()->create();
        $tarea = OnboardingTarea::factory()->create(['onboarding_id' => $onboarding->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.onboarding.tareas.destroy', [$onboarding, $tarea]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('rh_onboarding_tareas', ['id' => $tarea->id]);
    });

    test('tarea can be created with responsable', function () {
        $onboarding = Onboarding::factory()->create();
        $periodo = PeriodoLaboral::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.onboarding.tareas.store', $onboarding), [
                'titulo' => 'Entregar equipo',
                'responsable_periodo_id' => $periodo->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_onboarding_tareas', [
            'onboarding_id' => $onboarding->id,
            'titulo' => 'Entregar equipo',
            'responsable_periodo_id' => $periodo->id,
        ]);
    });

    test('evidencia can be uploaded to tarea', function () {
        Storage::fake('public');
        $onboarding = Onboarding::factory()->create();
        $tarea = OnboardingTarea::factory()->create(['onboarding_id' => $onboarding->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.onboarding.tareas.evidencia', [$onboarding, $tarea]), [
                'evidencia' => UploadedFile::fake()->create('evidencia.pdf', 100),
            ]);

        $response->assertRedirect();
        $tarea->refresh();
        expect($tarea->media)->not->toBeNull();
        Storage::disk('public')->assertExists($tarea->media->path);
    });

    test('crear onboarding copia las tareas de la plantilla del puesto', function () {
        $puesto = Puesto::factory()->create();
        OnboardingTareaPlantilla::factory()->create([
            'puesto_id' => $puesto->id,
            'titulo' => 'Inducción de seguridad',
            'dias_desde_inicio' => 1,
            'orden' => 0,
        ]);
        OnboardingTareaPlantilla::factory()->create([
            'puesto_id' => $puesto->id,
            'titulo' => 'Entregar uniforme',
            'dias_desde_inicio' => 3,
            'orden' => 1,
        ]);
        OnboardingTareaPlantilla::factory()->create([
            'puesto_id' => $puesto->id,
            'titulo' => 'Capacitación inicial',
            'dias_desde_inicio' => null,
            'orden' => 2,
        ]);

        $periodo = PeriodoLaboral::factory()->create(['puesto_id' => $puesto->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.onboarding', $periodo));

        $response->assertRedirect();
        $onboarding = $periodo->fresh()->onboarding;
        expect($onboarding)->not->toBeNull();
        expect($onboarding->tareas()->count())->toBe(3);

        $titulos = $onboarding->tareas()->pluck('titulo')->all();
        expect($titulos)->toContain('Inducción de seguridad');
        expect($titulos)->toContain('Entregar uniforme');
        expect($titulos)->toContain('Capacitación inicial');

        // Tarea con dias_desde_inicio=null queda sin fecha_vencimiento
        $sinFecha = $onboarding->tareas()->where('titulo', 'Capacitación inicial')->first();
        expect($sinFecha->fecha_vencimiento)->toBeNull();

        // Tarea con dias_desde_inicio=1 queda con fecha_vencimiento +1 dia
        $conFecha = $onboarding->tareas()->where('titulo', 'Inducción de seguridad')->first();
        expect($conFecha->fecha_vencimiento)->not->toBeNull();
    });

    test('crear onboarding sin plantilla deja onboarding vacio', function () {
        $puesto = Puesto::factory()->create(); // sin plantillas
        $periodo = PeriodoLaboral::factory()->create(['puesto_id' => $puesto->id]);

        $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.onboarding', $periodo))
            ->assertRedirect();

        $onboarding = $periodo->fresh()->onboarding;
        expect($onboarding)->not->toBeNull();
        expect($onboarding->tareas()->count())->toBe(0);
    });
});
