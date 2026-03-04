<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Rh\Actividad;
use App\Models\Rh\Candidatura;
use App\Models\Rh\DatosExtra;
use App\Models\Rh\DocumentoPuesto;
use App\Models\Rh\Onboarding;
use App\Models\Rh\OnboardingTarea;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\PermisoAusencia;
use App\Models\Rh\Persona;
use App\Models\Rh\PersonaDocumento;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\Requisicion;
use App\Models\Rh\RequisicionExtra;
use App\Models\Rh\Skill;
use App\Models\Rh\SkillDemostrada;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RhDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->limpiarDatos();

        $departamentos = $this->crearDepartamentos();
        $skills = $this->crearSkills();
        $requerimientos = $this->crearRequerimientos();
        $puestos = $this->crearPuestos($departamentos, $skills, $requerimientos);
        $personas = $this->crearPersonas();
        $periodos = $this->crearPeriodosLaborales($personas, $puestos);
        $this->crearOnboardings($periodos, $skills);
        $this->crearRequisiciones($puestos, $personas, $periodos);
        $this->crearPermisosAusencia($personas);
    }

    private function limpiarDatos(): void
    {
        DB::table('rh_onboarding_tareas')->delete();
        DB::table('rh_onboarding')->delete();
        DB::table('rh_candidaturas')->delete();
        DB::table('rh_requisicion_extra')->delete();
        DB::table('rh_requisiciones')->delete();
        DB::table('rh_skills_demostradas')->delete();
        DB::table('rh_requerimientos_demostrados')->delete();
        DB::table('rh_permisos_ausencia')->delete();
        DB::table('rh_periodos_laborales')->delete();
        DB::table('rh_persona_documentos')->delete();
        DB::table('rh_datos_extras')->delete();
        DB::table('rh_personas')->delete();
        DB::table('rh_actividades')->delete();
        DB::table('rh_documentos_puesto')->delete();
        DB::table('rh_puesto_skills')->delete();
        DB::table('rh_puesto_requerimientos')->delete();
        DB::table('rh_puestos')->delete();
        DB::table('rh_skills')->delete();
        DB::table('rh_requerimientos')->delete();
    }

    /** @return \Illuminate\Support\Collection<int, Departamento> */
    private function crearDepartamentos(): \Illuminate\Support\Collection
    {
        return collect([
            'Producción', 'Administración', 'Recursos Humanos', 'Calidad',
            'Almacén', 'Logística', 'Ingeniería', 'Ventas',
        ])->map(fn ($nombre) => Departamento::firstOrCreate(
            ['descripcion' => $nombre],
            ['manager' => fake()->name()]
        ));
    }

    /** @return \Illuminate\Support\Collection<int, Skill> */
    private function crearSkills(): \Illuminate\Support\Collection
    {
        $hardSkills = [
            'AutoCAD', 'SolidWorks', 'Excel Avanzado', 'SAP', 'Soldadura MIG',
            'Soldadura TIG', 'Lectura de Planos', 'Metrología', 'CNC', 'PLC',
            'Python', 'SQL', 'Power BI', 'Lean Manufacturing', 'Six Sigma',
        ];

        $softSkills = [
            'Liderazgo', 'Comunicación Efectiva', 'Trabajo en Equipo',
            'Resolución de Problemas', 'Adaptabilidad', 'Negociación',
            'Orientación a Resultados', 'Pensamiento Crítico',
        ];

        $skills = collect();

        foreach ($hardSkills as $nombre) {
            $skills->push(Skill::create(['nombre' => $nombre, 'tipo' => 'hard']));
        }

        foreach ($softSkills as $nombre) {
            $skills->push(Skill::create(['nombre' => $nombre, 'tipo' => 'soft']));
        }

        return $skills;
    }

    /** @return \Illuminate\Support\Collection<int, Requerimiento> */
    private function crearRequerimientos(): \Illuminate\Support\Collection
    {
        $requerimientos = [
            ['descripcion' => 'Licenciatura en Ingeniería Industrial', 'valor' => 'Titulado'],
            ['descripcion' => 'Licenciatura en Ingeniería Mecánica', 'valor' => 'Titulado'],
            ['descripcion' => 'Licenciatura en Administración', 'valor' => 'Titulado o Pasante'],
            ['descripcion' => 'Técnico en Soldadura', 'valor' => 'Certificado'],
            ['descripcion' => 'Preparatoria terminada', 'valor' => 'Certificado'],
            ['descripcion' => 'Experiencia mínima 2 años', 'valor' => '2 años'],
            ['descripcion' => 'Experiencia mínima 5 años', 'valor' => '5 años'],
            ['descripcion' => 'Manejo de montacargas', 'valor' => 'Licencia vigente'],
            ['descripcion' => 'Licencia de conducir', 'valor' => 'Vigente'],
            ['descripcion' => 'Disponibilidad de horario', 'valor' => 'Requerido'],
            ['descripcion' => 'Disponibilidad para viajar', 'valor' => 'Requerido'],
            ['descripcion' => 'Certificación ISO 9001', 'valor' => 'Deseable'],
        ];

        return collect($requerimientos)->map(fn ($r) => Requerimiento::create($r));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Departamento>  $departamentos
     * @param  \Illuminate\Support\Collection<int, Skill>  $skills
     * @param  \Illuminate\Support\Collection<int, Requerimiento>  $requerimientos
     * @return \Illuminate\Support\Collection<int, Puesto>
     */
    private function crearPuestos($departamentos, $skills, $requerimientos): \Illuminate\Support\Collection
    {
        $puestosData = [
            ['nombre' => 'Gerente de Producción', 'departamento' => 'Producción', 'codigo' => 'GP001', 'hora_entrada' => '07:00', 'hora_salida' => '16:00'],
            ['nombre' => 'Supervisor de Línea', 'departamento' => 'Producción', 'codigo' => 'SL001', 'hora_entrada' => '06:00', 'hora_salida' => '14:30'],
            ['nombre' => 'Operador CNC', 'departamento' => 'Producción', 'codigo' => 'OC001', 'hora_entrada' => '06:00', 'hora_salida' => '14:30'],
            ['nombre' => 'Soldador', 'departamento' => 'Producción', 'codigo' => 'SO001', 'hora_entrada' => '06:00', 'hora_salida' => '14:30'],
            ['nombre' => 'Inspector de Calidad', 'departamento' => 'Calidad', 'codigo' => 'IC001', 'hora_entrada' => '07:00', 'hora_salida' => '16:00'],
            ['nombre' => 'Coordinador de Calidad', 'departamento' => 'Calidad', 'codigo' => 'CC001', 'hora_entrada' => '08:00', 'hora_salida' => '17:00'],
            ['nombre' => 'Almacenista', 'departamento' => 'Almacén', 'codigo' => 'AL001', 'hora_entrada' => '07:00', 'hora_salida' => '16:00'],
            ['nombre' => 'Coordinador de Logística', 'departamento' => 'Logística', 'codigo' => 'CL001', 'hora_entrada' => '08:00', 'hora_salida' => '17:00'],
            ['nombre' => 'Ingeniero de Diseño', 'departamento' => 'Ingeniería', 'codigo' => 'ID001', 'hora_entrada' => '08:00', 'hora_salida' => '17:00'],
            ['nombre' => 'Contador', 'departamento' => 'Administración', 'codigo' => 'CO001', 'hora_entrada' => '08:00', 'hora_salida' => '17:00'],
            ['nombre' => 'Analista de RH', 'departamento' => 'Recursos Humanos', 'codigo' => 'AR001', 'hora_entrada' => '08:00', 'hora_salida' => '17:00'],
            ['nombre' => 'Ejecutivo de Ventas', 'departamento' => 'Ventas', 'codigo' => 'EV001', 'hora_entrada' => '08:30', 'hora_salida' => '17:30'],
        ];

        $depMap = $departamentos->keyBy('descripcion');
        $puestos = collect();
        $niveles = ['basico', 'intermedio', 'avanzado'];

        foreach ($puestosData as $data) {
            $puesto = Puesto::create([
                'departamento_id' => $depMap[$data['departamento']]->id,
                'nombre' => $data['nombre'],
                'descripcion' => fake()->paragraph(),
                'codigo' => $data['codigo'],
                'ubicacion' => fake()->randomElement(['Planta 1', 'Planta 2', 'Oficinas Centrales', 'Nave 3']),
                'hora_entrada' => $data['hora_entrada'],
                'hora_salida' => $data['hora_salida'],
            ]);

            // Asignar 3-5 skills aleatorios
            $puestoSkills = $skills->random(random_int(3, 5));
            foreach ($puestoSkills as $skill) {
                $puesto->skills()->attach($skill->id, [
                    'nivel_requerido' => fake()->randomElement($niveles),
                ]);
            }

            // Asignar 2-4 requerimientos aleatorios
            $puestoReqs = $requerimientos->random(random_int(2, 4));
            foreach ($puestoReqs as $req) {
                $puesto->requerimientos()->attach($req->id);
            }

            // Crear 3-5 actividades
            $actividades = [
                'Supervisar línea de producción', 'Operar maquinaria CNC', 'Realizar soldaduras',
                'Inspeccionar producto terminado', 'Elaborar reportes diarios', 'Coordinar entregas',
                'Diseñar planos técnicos', 'Gestionar inventarios', 'Atender auditorías',
                'Capacitar personal nuevo', 'Analizar indicadores de productividad',
                'Realizar mantenimiento preventivo', 'Preparar nómina quincenal',
            ];
            foreach (fake()->randomElements($actividades, random_int(3, 5)) as $act) {
                Actividad::create(['puesto_id' => $puesto->id, 'descripcion' => $act]);
            }

            // Crear 1-2 documentos del puesto
            $docs = [
                ['nombre_reporte' => 'Reporte de producción diaria', 'frecuencia_entrega' => 'diaria', 'cargo_entrega' => 'Gerente'],
                ['nombre_reporte' => 'Reporte de calidad semanal', 'frecuencia_entrega' => 'semanal', 'cargo_entrega' => 'Coordinador'],
                ['nombre_reporte' => 'Inventario mensual', 'frecuencia_entrega' => 'mensual', 'cargo_entrega' => 'Almacenista'],
                ['nombre_reporte' => 'Indicadores KPI', 'frecuencia_entrega' => 'mensual', 'cargo_entrega' => 'Gerente'],
            ];
            foreach (fake()->randomElements($docs, random_int(1, 2)) as $doc) {
                DocumentoPuesto::create(array_merge($doc, ['puesto_id' => $puesto->id]));
            }

            $puestos->push($puesto);
        }

        // Asignar jefes: primeros 2 puestos son de nivel gerencia, el resto reporta a ellos
        $gerente = $puestos->first();
        foreach ($puestos->skip(2) as $puesto) {
            $puesto->update(['puesto_jefe_id' => fake()->randomElement([$gerente->id, $puestos[1]->id])]);
        }

        return $puestos;
    }

    /** @return \Illuminate\Support\Collection<int, Persona> */
    private function crearPersonas(): \Illuminate\Support\Collection
    {
        $personasData = [
            ['nombre' => 'Carlos', 'apellido' => 'Méndez García'],
            ['nombre' => 'Ana María', 'apellido' => 'Ríos Hernández'],
            ['nombre' => 'Jorge', 'apellido' => 'Salinas Montoya'],
            ['nombre' => 'Lucía', 'apellido' => 'Domínguez Torres'],
            ['nombre' => 'Roberto', 'apellido' => 'Guzmán Pérez'],
            ['nombre' => 'Patricia', 'apellido' => 'Velázquez Cruz'],
            ['nombre' => 'Fernando', 'apellido' => 'Castillo López'],
            ['nombre' => 'Diana', 'apellido' => 'Morales Ramírez'],
            ['nombre' => 'Miguel', 'apellido' => 'Ortiz Sánchez'],
            ['nombre' => 'Gabriela', 'apellido' => 'Navarro Flores'],
            ['nombre' => 'Alejandro', 'apellido' => 'Herrera Martínez'],
            ['nombre' => 'Sofía', 'apellido' => 'Aguilar Ruiz'],
            ['nombre' => 'Raúl', 'apellido' => 'Contreras Díaz'],
            ['nombre' => 'Mariana', 'apellido' => 'Espinoza Vargas'],
            ['nombre' => 'Héctor', 'apellido' => 'Jiménez Reyes'],
            ['nombre' => 'Valeria', 'apellido' => 'Paredes Luna'],
            ['nombre' => 'Ricardo', 'apellido' => 'Fuentes Medina'],
            ['nombre' => 'Claudia', 'apellido' => 'Soto Rojas'],
            ['nombre' => 'Eduardo', 'apellido' => 'Delgado Castro'],
            ['nombre' => 'Adriana', 'apellido' => 'Pineda Gómez'],
        ];

        $tiposDoc = ['ine', 'curp', 'rfc', 'comprobante_domicilio', 'acta_nacimiento'];

        return collect($personasData)->map(function ($data) use ($tiposDoc) {
            $persona = Persona::create([
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'email' => strtolower(str_replace(' ', '', $data['nombre'])).'.'.strtolower(explode(' ', $data['apellido'])[0]).'@steelex.com.mx',
                'telefono' => fake()->phoneNumber(),
                'fecha_nacimiento' => fake()->dateTimeBetween('-50 years', '-20 years'),
            ]);

            // Datos extra
            DatosExtra::create([
                'persona_id' => $persona->id,
                'estado_civil' => fake()->randomElement(['soltero', 'casado', 'divorciado']),
                'hijos' => fake()->numberBetween(0, 4),
                'localidad' => fake()->city(),
                'domicilio' => fake()->address(),
                'cp' => fake()->numerify('#####'),
                'curp' => strtoupper(fake()->regexify('[A-Z]{4}[0-9]{6}[A-Z]{6}[0-9]{2}')),
                'rfc' => strtoupper(fake()->regexify('[A-Z]{4}[0-9]{6}[A-Z0-9]{3}')),
                'imss' => fake()->numerify('###########'),
                'cuenta_banco' => fake()->numerify('################'),
                'banco_op' => fake()->randomElement(['BBVA', 'Banorte', 'Santander', 'HSBC', 'Scotiabank']),
            ]);

            // 2-4 documentos personales
            foreach (fake()->randomElements($tiposDoc, random_int(2, 4)) as $tipo) {
                PersonaDocumento::create([
                    'persona_id' => $persona->id,
                    'tipo_documento' => $tipo,
                    'nombre_archivo' => $tipo.'_'.strtolower(explode(' ', $data['apellido'])[0]).'.pdf',
                    'ruta_archivo' => 'rh/documentos/'.$persona->id.'/'.$tipo.'.pdf',
                    'extension' => 'pdf',
                    'tamano' => random_int(50000, 3000000),
                ]);
            }

            return $persona;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Persona>  $personas
     * @param  \Illuminate\Support\Collection<int, Puesto>  $puestos
     * @return \Illuminate\Support\Collection<int, PeriodoLaboral>
     */
    private function crearPeriodosLaborales($personas, $puestos): \Illuminate\Support\Collection
    {
        $periodos = collect();
        $contratos = ['indefinido', 'temporal', 'prueba'];

        // Primeras 15 personas: activas con periodo laboral
        foreach ($personas->take(15) as $i => $persona) {
            $puesto = $puestos[$i % $puestos->count()];
            $fechaInicio = Carbon::now()->subMonths(random_int(1, 24))->subDays(random_int(0, 28));

            $periodo = PeriodoLaboral::create([
                'persona_id' => $persona->id,
                'puesto_id' => $puesto->id,
                'fecha_inicio' => $fechaInicio,
                'estado' => 'activo',
                'salario' => fake()->randomFloat(2, 8000, 65000),
                'tipo_contrato' => fake()->randomElement($contratos),
            ]);

            $periodos->push($periodo);
        }

        // Siguientes 3 personas: periodo terminado
        foreach ($personas->slice(15, 3) as $persona) {
            $puesto = $puestos->random();
            $fechaInicio = Carbon::now()->subMonths(random_int(12, 36));
            $fechaFin = $fechaInicio->copy()->addMonths(random_int(3, 12));

            $periodo = PeriodoLaboral::create([
                'persona_id' => $persona->id,
                'puesto_id' => $puesto->id,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estado' => 'terminado',
                'salario' => fake()->randomFloat(2, 8000, 45000),
                'tipo_contrato' => 'temporal',
            ]);

            $periodos->push($periodo);
        }

        // Últimas 2 personas: sin periodo (candidatos puros)

        return $periodos;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PeriodoLaboral>  $periodos
     * @param  \Illuminate\Support\Collection<int, Skill>  $skills
     */
    private function crearOnboardings($periodos, $skills): void
    {
        // Onboarding para los 5 periodos más recientes (activos)
        $periodosRecientes = $periodos->where('estado', 'activo')->sortByDesc('fecha_inicio')->take(5);

        $tareasBase = [
            'Recorrido por instalaciones',
            'Entrega de EPP',
            'Firma de contrato laboral',
            'Alta en sistema de nómina',
            'Capacitación de seguridad industrial',
            'Capacitación del puesto',
            'Entrega de herramientas de trabajo',
            'Configurar accesos al sistema',
            'Presentación con el equipo',
            'Entrega de reglamento interno',
            'Asignación de locker y vestidor',
            'Alta en seguro social (IMSS)',
        ];

        foreach ($periodosRecientes as $periodo) {
            $fechaInicio = Carbon::parse($periodo->fecha_inicio);

            $onboarding = Onboarding::create([
                'periodo_id' => $periodo->id,
                'fecha_inicio' => $fechaInicio,
                'progreso' => 0,
            ]);

            // Asignar 6-10 tareas aleatorias
            $tareas = fake()->randomElements($tareasBase, random_int(6, 10));
            $completadas = 0;
            $total = count($tareas);

            foreach ($tareas as $j => $titulo) {
                $estaCompletada = $j < (int) ($total * fake()->randomFloat(2, 0.3, 0.9));

                OnboardingTarea::create([
                    'onboarding_id' => $onboarding->id,
                    'titulo' => $titulo,
                    'descripcion' => fake()->optional(0.5)->sentence(),
                    'completada' => $estaCompletada,
                    'fecha_vencimiento' => $fechaInicio->copy()->addDays(random_int(1, 30)),
                    'fecha_completada' => $estaCompletada ? $fechaInicio->copy()->addDays(random_int(1, 14)) : null,
                ]);

                if ($estaCompletada) {
                    $completadas++;
                }
            }

            $onboarding->update(['progreso' => $total > 0 ? (int) round(($completadas / $total) * 100) : 0]);

            // Skills demostradas para la persona del periodo
            $personaSkills = $skills->random(random_int(3, 6));
            foreach ($personaSkills as $skill) {
                SkillDemostrada::create([
                    'persona_id' => $periodo->persona_id,
                    'skill_id' => $skill->id,
                    'onboarding' => fake()->boolean(70),
                    'cumple' => fake()->boolean(80),
                    'nivel_alcanzado' => fake()->randomElement(['basico', 'intermedio', 'avanzado']),
                    'evidencia' => fake()->optional(0.3)->sentence(),
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Puesto>  $puestos
     * @param  \Illuminate\Support\Collection<int, Persona>  $personas
     * @param  \Illuminate\Support\Collection<int, PeriodoLaboral>  $periodos
     */
    private function crearRequisiciones($puestos, $personas, $periodos): void
    {
        $estados = ['borrador', 'abierta', 'en_proceso', 'cerrada', 'cancelada'];
        $periodosActivos = $periodos->where('estado', 'activo');
        $personasSinPeriodo = $personas->slice(15); // últimas 5 personas como candidatos

        for ($i = 1; $i <= 8; $i++) {
            $puesto = $puestos->random();
            $estado = fake()->randomElement($estados);
            $fechaCreacion = Carbon::now()->subDays(random_int(5, 120));

            $requisicion = Requisicion::create([
                'folio' => 'REQ-'.date('Y').'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'puesto_id' => $puesto->id,
                'solicitada_por_periodo_id' => $periodosActivos->random()->id,
                'cantidad' => random_int(1, 3),
                'estado' => $estado,
                'tipo_requisicion' => fake()->randomElement(['nueva', 'reemplazo', 'temporal']),
                'justificacion' => fake()->paragraph(),
                'nombre_solicitante' => fake()->name(),
                'salario' => fake()->randomFloat(2, 10000, 60000),
                'fecha_creacion' => $fechaCreacion,
                'fecha_cierre' => $estado === 'cerrada' ? $fechaCreacion->copy()->addDays(random_int(15, 60)) : null,
            ]);

            // Datos extra de requisición
            RequisicionExtra::create([
                'requisicion_id' => $requisicion->id,
                'salario_mensual' => $requisicion->salario,
                'salario_diario' => round($requisicion->salario / 30, 2),
                'periodicidad_pago' => fake()->randomElement(['quincenal', 'mensual']),
                'tipo_jornada' => fake()->randomElement(['diurna', 'nocturna', 'mixta']),
                'horario' => fake()->randomElement(['Lunes a Viernes 8:00-17:00', 'Lunes a Sábado 6:00-14:30']),
                'observaciones' => fake()->optional(0.4)->sentence(),
            ]);

            // 2-4 candidaturas por requisición abierta/en_proceso
            if (in_array($estado, ['abierta', 'en_proceso', 'cerrada'])) {
                $candidatos = $personasSinPeriodo->merge($personas->random(3))->unique('id')->take(random_int(2, 4));

                foreach ($candidatos as $persona) {
                    Candidatura::create([
                        'requisicion_id' => $requisicion->id,
                        'persona_id' => $persona->id,
                        'fecha_aplicacion' => $fechaCreacion->copy()->addDays(random_int(1, 20)),
                        'porcentaje_match' => fake()->randomFloat(2, 40, 98),
                        'porcentaje_skills' => fake()->randomFloat(2, 30, 100),
                        'porcentaje_requisitos' => fake()->randomFloat(2, 30, 100),
                        'notas' => fake()->optional(0.4)->sentence(),
                    ]);
                }
            }
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Persona>  $personas */
    private function crearPermisosAusencia($personas): void
    {
        $tipos = ['vacaciones', 'personal', 'medico', 'maternidad', 'paternidad'];
        $modalidades = ['con_goce', 'sin_goce', 'a_cuenta_vacaciones'];
        $personasActivas = $personas->take(15);

        for ($i = 1; $i <= 12; $i++) {
            $persona = $personasActivas->random();

            PermisoAusencia::create([
                'folio' => 'PA-'.date('Y').'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'numero_empleado' => 'EMP-'.str_pad((string) $persona->id, 4, '0', STR_PAD_LEFT),
                'nombres' => $persona->nombre,
                'apellidos' => $persona->apellido,
                'departamento' => fake()->randomElement(['Producción', 'Administración', 'Calidad', 'Almacén', 'Ingeniería']),
                'gerente' => fake()->name(),
                'tipo' => fake()->randomElement($tipos),
                'modalidad' => fake()->randomElement($modalidades),
                'razon' => fake()->sentence(),
                'fecha_permiso' => Carbon::now()->subDays(random_int(0, 90)),
            ]);
        }
    }
}
