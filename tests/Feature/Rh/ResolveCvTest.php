<?php

use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\RequerimientoDemostrado;
use App\Models\Rh\Requisicion;
use App\Models\Rh\Skill;
use App\Services\Rh\Cv\CvProcessor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Setup Ollama config a un host invented para garantizar que Http::fake intercepta
    config(['services.ollama.url' => 'http://ollama-fake.test/api/generate']);
    config(['services.ollama.timeout' => 5]);
    config(['services.ollama.max_retries' => 1]);
});

it('hace nada si no hay CVs pendientes', function () {
    /** @var CvProcessor $processor */
    $processor = app(CvProcessor::class);
    expect($processor->procesarSiguiente())->toBeNull();
});

it('procesa paso 2 (datos personales) usando Http::fake', function () {
    $persona = Persona::factory()->create([
        'nombre' => '',
        'apellido' => '',
        'cv_estado' => 'texto_extraido',
        'texto_cv' => 'Jorge Briones, ingeniero, jorge@example.com',
    ]);
    // Necesita media para pasar el filtro whereHas('media')
    $persona->media()->create([
        'descripcion' => 'cv',
        'nombre_original' => 'cv.pdf',
        'path' => 'rh/cv/fake.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    Http::fake([
        'ollama-fake.test/*' => Http::response([
            'response' => json_encode([
                'nombre' => 'Jorge',
                'apellido' => 'Briones',
                'email' => 'jorge@example.com',
                'curp' => 'BRJM850531HCRRRG09',
            ]),
        ]),
    ]);

    /** @var CvProcessor $processor */
    $processor = app(CvProcessor::class);
    expect($processor->procesarSiguiente())->toBe($persona->id);

    $persona->refresh();
    expect($persona->cv_estado)->toBe('datos_extraidos');
    expect($persona->nombre)->toBe('Jorge');
    expect($persona->apellido)->toBe('Briones');
    expect($persona->curp)->toBe('BRJM850531HCRRRG09');
});

it('procesa paso 3 (requerimientos) e inserta en rh_requerimientos_demostrados', function () {
    $req = Requerimiento::factory()->create(['descripcion' => 'Manejo de Excel']);
    $persona = Persona::factory()->create([
        'cv_estado' => 'datos_extraidos',
        'texto_cv' => 'Experto en Excel y tablas dinámicas',
    ]);
    $persona->media()->create([
        'descripcion' => 'cv', 'nombre_original' => 'cv.pdf', 'path' => 'x', 'mime' => 'application/pdf', 'size' => 1,
    ]);

    Http::fake([
        'ollama-fake.test/*' => Http::response([
            'response' => json_encode([['id' => $req->id, 'cumple' => true]]),
        ]),
    ]);

    app(CvProcessor::class)->procesarSiguiente();
    $persona->refresh();

    expect($persona->cv_estado)->toBe('requisitos_procesados');
    $this->assertDatabaseHas('rh_requerimientos_demostrados', [
        'persona_id' => $persona->id,
        'requerimiento_id' => $req->id,
        'cumple' => true,
    ]);
});

it('procesa paso 4, completa la persona y calcula porcentajes de candidatura', function () {
    $puesto = Puesto::factory()->create();
    $skill = Skill::factory()->create();
    $req = Requerimiento::factory()->create();
    $puesto->skills()->attach($skill, ['nivel_requerido' => 'basico']);
    $puesto->requerimientos()->attach($req);

    $requisicion = Requisicion::factory()->create(['puesto_id' => $puesto->id]);
    $persona = Persona::factory()->create([
        'cv_estado' => 'requisitos_procesados',
        'texto_cv' => 'CV con experiencia en PHP',
    ]);
    $persona->media()->create([
        'descripcion' => 'cv', 'nombre_original' => 'cv.pdf', 'path' => 'x', 'mime' => 'application/pdf', 'size' => 1,
    ]);
    $candidatura = Candidatura::factory()->create([
        'requisicion_id' => $requisicion->id,
        'persona_id' => $persona->id,
    ]);
    // Marcar el requerimiento como cumplido para que calcularPorcentajes tenga señal
    RequerimientoDemostrado::create([
        'persona_id' => $persona->id,
        'requerimiento_id' => $req->id,
        'cumple' => true,
    ]);

    Http::fake([
        'ollama-fake.test/*' => Http::response([
            'response' => json_encode([['id' => $skill->id, 'cumple' => true]]),
        ]),
    ]);

    app(CvProcessor::class)->procesarSiguiente();
    $persona->refresh();
    $candidatura->refresh();

    expect($persona->cv_estado)->toBe('completado');
    expect($persona->cv_procesado_at)->not->toBeNull();
    $this->assertDatabaseHas('rh_skills_demostradas', [
        'persona_id' => $persona->id,
        'skill_id' => $skill->id,
        'cumple' => true,
    ]);
    expect((float) $candidatura->porcentaje_skills)->toBe(100.00);
    expect((float) $candidatura->porcentaje_requisitos)->toBe(100.00);
    expect((float) $candidatura->porcentaje_match)->toBe(100.00);
});

it('si Ollama falla, retrocede al estado previo e incrementa reintentos', function () {
    $persona = Persona::factory()->create([
        'cv_estado' => 'texto_extraido',
        'texto_cv' => 'algun cv',
        'reintentos' => 0,
    ]);
    $persona->media()->create([
        'descripcion' => 'cv', 'nombre_original' => 'cv.pdf', 'path' => 'x', 'mime' => 'application/pdf', 'size' => 1,
    ]);

    Http::fake([
        'ollama-fake.test/*' => Http::response(['error' => 'down'], 500),
    ]);

    app(CvProcessor::class)->procesarSiguiente();
    $persona->refresh();

    // CvDataExtractor con respuesta vacía guarda lo que pudo (probablemente nada).
    // El estado se queda en datos_extraidos solo si la llamada Ollama exitosa parseo OK.
    // Aquí Ollama dio 500 → excepción → manejarError lo retrocede a 'texto_extraido' con reintentos+1.
    expect($persona->cv_estado)->toBe('texto_extraido');
    expect($persona->reintentos)->toBe(1);
    expect($persona->error_procesamiento)->not->toBeNull();
});
