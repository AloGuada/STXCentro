<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migra datos del módulo Calidad desde la DB vieja (swapi) a la nueva (mono).
 *
 * Uso:
 *   1. Configura las variables SWAPI_DB_* en .env
 *   2. php artisan db:seed --class=CalMigracionSeeder
 *
 * Mapeo de tablas:
 *   obras          → obras            (match por campo 'no')
 *   users          → usuarios         (inspectores, se crean con UUID)
 *   soldadores     → cal_soldadores
 *   etapas         → cal_etapas
 *   piezas         → cal_piezas
 *   piezas_planos  → cal_piezas_planos
 *   reportes       → cal_reportes
 *   flechas        → cal_flechas
 */
class CalMigracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $old = DB::connection('swapi');

        // ─── 0. Limpiar tablas cal_* (orden inverso a dependencias FK) ──
        $this->command->info('Limpiando tablas de calidad...');
        DB::table('cal_flechas')->delete();
        DB::table('cal_reportes')->delete();
        DB::table('cal_piezas_planos')->delete();
        DB::table('cal_piezas')->delete();
        DB::table('cal_etapas')->delete();
        DB::table('cal_soldadores')->delete();

        // ─── 1. Mapear obras por campo 'no' ─────────────────────────────
        $this->command->info('Mapeando obras...');
        $obrasViejas = $old->table('obras')->get();
        $obraMap = []; // old_id → new_id

        foreach ($obrasViejas as $obraVieja) {
            $obraNueva = DB::table('obras')->where('no', $obraVieja->no)->first();

            if (! $obraNueva) {
                $this->command->warn("  Obra '{$obraVieja->no}' ({$obraVieja->descripcion}) no existe en mono. Saltando.");

                continue;
            }

            $obraMap[$obraVieja->id] = $obraNueva->id;
        }
        $this->command->info('  Obras mapeadas: '.count($obraMap));

        // ─── 2. Migrar inspectores (users → usuarios) ───────────────────
        $this->command->info('Migrando inspectores...');
        $usersViejos = $old->table('users')->get();
        $inspectorMap = []; // old_id → new_uuid

        foreach ($usersViejos as $user) {
            $existente = DB::table('usuarios')->where('email', $user->email)->first();

            if ($existente) {
                $inspectorMap[$user->id] = $existente->id;
                $this->command->line("  Inspector '{$user->name}' ya existe → {$existente->id}");
            } else {
                $uuid = Str::uuid()->toString();
                DB::table('usuarios')->insert([
                    'id' => $uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);
                $inspectorMap[$user->id] = $uuid;
                $this->command->line("  Inspector '{$user->name}' creado → {$uuid}");
            }
        }

        // ─── 3. Migrar soldadores ────────────────────────────────────────
        $this->command->info('Migrando soldadores...');
        $soldadoresViejos = $old->table('soldadores')->get();
        $soldadorMap = []; // old_id → new_id

        foreach ($soldadoresViejos as $s) {
            $newId = DB::table('cal_soldadores')->insertGetId([
                'nombre' => $s->nombre,
                'certificacion' => $s->certificacion,
                'activo' => $s->activo ?? true,
                'created_at' => $s->created_at,
                'updated_at' => $s->updated_at,
            ]);
            $soldadorMap[$s->id] = $newId;
        }
        $this->command->info('  Soldadores: '.count($soldadorMap));

        // ─── 4. Migrar etapas ────────────────────────────────────────────
        $this->command->info('Migrando etapas...');
        $etapasViejas = $old->table('etapas')->get();
        $etapaMap = []; // old_id → new_id

        foreach ($etapasViejas as $e) {
            if (! isset($obraMap[$e->obra_id])) {
                $this->command->warn("  Etapa '{$e->descripcion}' tiene obra_id={$e->obra_id} sin mapear. Saltando.");

                continue;
            }

            $newId = DB::table('cal_etapas')->insertGetId([
                'descripcion' => $e->descripcion,
                'obra_id' => $obraMap[$e->obra_id],
                'created_at' => $e->created_at,
                'updated_at' => $e->updated_at,
            ]);
            $etapaMap[$e->id] = $newId;
        }
        $this->command->info('  Etapas: '.count($etapaMap));

        // ─── 5. Migrar piezas ────────────────────────────────────────────
        $this->command->info('Migrando piezas...');
        $piezasViejas = $old->table('piezas')->get();
        $piezaMap = []; // old_id → new_id

        foreach ($piezasViejas as $p) {
            if (! isset($etapaMap[$p->etapa_id])) {
                $this->command->warn("  Pieza '{$p->marca}' tiene etapa_id={$p->etapa_id} sin mapear. Saltando.");

                continue;
            }

            $newId = DB::table('cal_piezas')->insertGetId([
                'marca' => $p->marca,
                'cantidad' => $p->cantidad,
                'etapa_id' => $etapaMap[$p->etapa_id],
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ]);
            $piezaMap[$p->id] = $newId;
        }
        $this->command->info('  Piezas: '.count($piezaMap));

        // ─── 6. Migrar piezas_planos ─────────────────────────────────────
        $this->command->info('Migrando planos...');
        $planosViejos = $old->table('piezas_planos')->get();
        $planoMap = []; // old_id → new_id

        foreach ($planosViejos as $pp) {
            if (! isset($piezaMap[$pp->pieza_id])) {
                $this->command->warn("  Plano id={$pp->id} tiene pieza_id={$pp->pieza_id} sin mapear. Saltando.");

                continue;
            }

            $newId = DB::table('cal_piezas_planos')->insertGetId([
                'pieza_id' => $piezaMap[$pp->pieza_id],
                'pdf_path' => $pp->pdf_path ?? null,
                'plano_normal' => $pp->plano_normal ?? null,
                'dwg_path' => $pp->dwg_path ?? null,
                'version' => $pp->version ?? 1,
                'created_at' => $pp->created_at,
                'updated_at' => $pp->updated_at,
            ]);
            $planoMap[$pp->id] = $newId;
        }
        $this->command->info('  Planos: '.count($planoMap));

        // ─── 7. Migrar reportes ──────────────────────────────────────────
        $this->command->info('Migrando reportes...');
        $reportesViejos = $old->table('reportes')->get();
        $reporteMap = []; // old_id → new_id

        foreach ($reportesViejos as $r) {
            if (! isset($planoMap[$r->plano_id])) {
                $this->command->warn("  Reporte id={$r->id} tiene plano_id={$r->plano_id} sin mapear. Saltando.");

                continue;
            }

            $inspectorUuid = isset($inspectorMap[$r->inspector_id]) ? $inspectorMap[$r->inspector_id] : null;

            $soldadorNuevoId = null;
            if (! empty($r->soldador_id) && isset($soldadorMap[$r->soldador_id])) {
                $soldadorNuevoId = $soldadorMap[$r->soldador_id];
            }

            $newId = DB::table('cal_reportes')->insertGetId([
                'plano_id' => $planoMap[$r->plano_id],
                'strumis_id' => $r->strumis_id,
                'consecutivo' => (string) $r->consecutivo,
                'inspector_id' => $inspectorUuid,
                'plantilla' => $r->plantilla ? '1' : null,
                'aprobado' => $r->aprobado,
                'rechazado' => $r->rechazado,
                'es_plantilla' => (bool) $r->es_plantilla,
                'linea' => $r->linea,
                'modulo' => $r->modulo,
                'comentario' => $r->comentario,
                'folio' => $r->folio ?? null,
                'soldador_id' => $soldadorNuevoId,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
            $reporteMap[$r->id] = $newId;
        }
        $this->command->info('  Reportes: '.count($reporteMap));

        // ─── 8. Migrar flechas ───────────────────────────────────────────
        $this->command->info('Migrando flechas...');
        $flechasViejas = $old->table('flechas')->get();
        $flechaCount = 0;

        foreach ($flechasViejas as $f) {
            if (! isset($reporteMap[$f->reporte_id])) {
                $this->command->warn("  Flecha id={$f->id} tiene reporte_id={$f->reporte_id} sin mapear. Saltando.");

                continue;
            }

            DB::table('cal_flechas')->insert([
                'reporte_id' => $reporteMap[$f->reporte_id],
                'inicio_x' => $f->inicio_x,
                'inicio_y' => $f->inicio_y,
                'fin_x' => $f->fin_x,
                'fin_y' => $f->fin_y,
                'esdoble' => (bool) $f->esdoble,
                'tipo' => $f->tipo,
                'show_number' => true,
                'pagina' => $f->pagina ?? 1,
                'created_at' => $f->created_at,
                'updated_at' => $f->updated_at,
            ]);
            $flechaCount++;
        }
        $this->command->info("  Flechas: {$flechaCount}");

        $this->command->newLine();
        $this->command->info('Migración de Calidad completada.');
    }
}
