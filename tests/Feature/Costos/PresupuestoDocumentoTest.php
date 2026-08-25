<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['ver', 'editar'] as $accion) {
        Permission::firstOrCreate(['name' => "costos.obra-rubros.{$accion}", 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.obra-rubros.ver', 'costos.obra-rubros.editar']);
});

describe('documento del presupuesto', function () {
    it('guarda el pdf y lo deja colgado del presupuesto', function () {
        Storage::fake('public');

        $presupuesto = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.documento', $presupuesto), [
                'documento' => UploadedFile::fake()->create('presupuesto.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $media = $presupuesto->fresh()->media;

        expect($media)->toHaveCount(1);
        expect($media->first()->descripcion)->toBe(DocumentoTipo::PresupuestoDocumento->value);
        expect($media->first()->nombre_original)->toBe('presupuesto.pdf');
        Storage::disk('public')->assertExists($media->first()->path);
    });

    it('el documento nuevo reemplaza al anterior, no se acumulan', function () {
        Storage::fake('public');

        $presupuesto = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.documento', $presupuesto), [
                'documento' => UploadedFile::fake()->create('viejo.pdf', 50, 'application/pdf'),
            ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.documento', $presupuesto), [
                'documento' => UploadedFile::fake()->create('nuevo.pdf', 50, 'application/pdf'),
            ]);

        $media = $presupuesto->fresh()->media;

        expect($media)->toHaveCount(1);
        expect($media->first()->nombre_original)->toBe('nuevo.pdf');
    });

    it('sólo acepta pdf', function () {
        Storage::fake('public');

        $presupuesto = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.documento', $presupuesto), [
                'documento' => UploadedFile::fake()->create('presupuesto.xlsx', 50),
            ])
            ->assertSessionHasErrors('documento');

        expect($presupuesto->fresh()->media)->toHaveCount(0);
    });

    it('sin permiso de editar no se puede subir', function () {
        Storage::fake('public');

        $mirón = User::factory()->create();
        $mirón->givePermissionTo('costos.obra-rubros.ver');

        $presupuesto = Presupuesto::factory()->paraObra(Obra::factory()->create())->create();

        $this->actingAs($mirón)
            ->post(route('admin.costos.presupuestos.documento', $presupuesto), [
                'documento' => UploadedFile::fake()->create('presupuesto.pdf', 50, 'application/pdf'),
            ])
            ->assertForbidden();
    });

    it('obras activas trae el documento de cada presupuesto', function () {
        Storage::fake('public');

        $conDocumento = Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Alfa']))->create();
        Presupuesto::factory()->paraObra(Obra::factory()->create(['descripcion' => 'Bravo']))->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.documento', $conDocumento), [
                'documento' => UploadedFile::fake()->create('alfa.pdf', 50, 'application/pdf'),
            ]);

        $this->actingAs($this->user)
            ->get(route('admin.costos.obras-activas.index', ['sort_by' => 'descripcion', 'sort_dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('presupuestos.data.0.documento.nombre', 'alfa.pdf')
                ->where('presupuestos.data.1.documento', null)
            );
    });
});

describe('impresión de obras activas', function () {
    it('devuelve un pdf con las obras activas', function () {
        Presupuesto::factory()->paraObra(Obra::factory()->create())->create();

        $respuesta = $this->actingAs($this->user)
            ->get(route('admin.costos.obras-activas.pdf'));

        $respuesta->assertOk();
        expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
    });

    it('respeta la pestaña de cerradas', function () {
        Presupuesto::factory()->paraObra(Obra::factory()->create())->cerrado()->create();

        $this->actingAs($this->user)
            ->get(route('admin.costos.obras-activas.pdf', ['estatus' => 'cerrado']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('sin permisos de costos no se imprime', function () {
        Permission::firstOrCreate(['name' => 'prod.destajos.ver', 'guard_name' => 'web']);
        $ajeno = User::factory()->create();
        $ajeno->givePermissionTo('prod.destajos.ver');

        $this->actingAs($ajeno)
            ->get(route('admin.costos.obras-activas.pdf'))
            ->assertForbidden();
    });
});
