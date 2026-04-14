import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BookOpenIcon, BriefcaseIcon, CheckSquareIcon, ClipboardListIcon, FileSignatureIcon, UserPlusIcon, UsersIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Documentación', href: '/admin/documentacion/rh' },
    { title: 'Recursos Humanos', href: '/admin/documentacion/rh' },
];

type PasoFlujo = {
    numero: number;
    titulo: string;
    descripcion: string;
};

const flujoPuestos: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Crear el puesto',
        descripcion:
            'Desde Recursos Humanos → Puestos se registra un puesto con nombre, código, descripción, departamento, ubicación y horario de entrada/salida. Opcionalmente se define un "puesto jefe" para jerarquía organizacional.',
    },
    {
        numero: 2,
        titulo: 'Definir skills requeridos',
        descripcion:
            'Dentro del puesto se agregan las habilidades (técnicas o blandas) con un nivel requerido: básico, intermedio o avanzado. Estos skills se usan para calcular la compatibilidad con los candidatos al procesar CVs con IA.',
    },
    {
        numero: 3,
        titulo: 'Agregar requerimientos',
        descripcion:
            'Se registran los requisitos formales: certificaciones, grados académicos, experiencia previa, licencias, idiomas, etc. Cada requerimiento tiene descripción y valor esperado.',
    },
    {
        numero: 4,
        titulo: 'Definir actividades y documentos del puesto',
        descripcion:
            'Se listan las actividades diarias/responsabilidades del puesto y los reportes que la persona debe entregar periódicamente (nombre, frecuencia y cargo al que entrega). Esta información alimenta al contrato y al onboarding.',
    },
];

const flujoRequisicion: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Crear la requisición',
        descripcion:
            'Desde Requisiciones → Nueva se registra el folio (auto-generado REQ-YYYY-0001), el puesto a cubrir, cantidad de vacantes, salario ofrecido, tipo de contrato, fechas y datos del solicitante y responsable de entrevista.',
    },
    {
        numero: 2,
        titulo: 'Configurar datos extra (RequisicionExtra)',
        descripcion:
            'Se agregan beneficios adicionales, esquema de salario, frecuencia de pago, bonos, horario, tipo de turno, prestaciones y notas. Esta información es la que verá el candidato potencial.',
    },
    {
        numero: 3,
        titulo: 'Activar procesamiento IA (opcional)',
        descripcion:
            'Si se marca "procesar_ia", al subir el CV de un candidato se extraerá el texto automáticamente y se calculará el porcentaje de match contra los skills y requerimientos del puesto.',
    },
    {
        numero: 4,
        titulo: 'Abrir la requisición',
        descripcion:
            'El estado pasa de "borrador" a "abierta" — desde este momento puede recibir candidaturas. Cuando se cierra el proceso (se contrata el número de vacantes), pasa a "en_proceso" o se cierra.',
    },
];

const flujoPersonas: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Registrar datos básicos',
        descripcion:
            'En Personas → Nueva se registra nombre, apellido, email, teléfono, fecha de nacimiento y foto de perfil. Cada persona es única en el sistema y puede tener múltiples candidaturas y períodos laborales.',
    },
    {
        numero: 2,
        titulo: 'Subir y procesar el CV',
        descripcion:
            'Se sube el PDF del CV al perfil de la persona. Si la requisición tenía IA habilitada, el CV pasa a estado "pendiente" y el sistema lo procesa extrayendo el texto y calculando el match con los requerimientos del puesto.',
    },
    {
        numero: 3,
        titulo: 'Completar Datos Extra',
        descripcion:
            'Al contratar (o antes), se capturan los datos extendidos: estado civil, hijos, localidad, domicilio, CP, nombres de padres, cuenta bancaria, CURP, RFC, NSS IMSS, INE, números Infonavit/Fonacot. Estos son obligatorios para generar el contrato PDF.',
    },
    {
        numero: 4,
        titulo: 'Agregar documentos y contactos de emergencia',
        descripcion:
            'Se pueden adjuntar documentos (INE, comprobantes, certificaciones) con fecha de vencimiento opcional, y registrar contactos de emergencia (nombre, parentesco, teléfono).',
    },
];

const flujoPostulacion: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Acceder a la requisición abierta',
        descripcion:
            'Desde Requisiciones → ver una requisición "abierta" o "en_proceso", se accede a la pestaña Candidatos para gestionar postulaciones.',
    },
    {
        numero: 2,
        titulo: 'Agregar candidatura (Candidatura)',
        descripcion:
            'Se selecciona una persona ya registrada y se crea una Candidatura vinculando persona ↔ requisición. El sistema filtra automáticamente personas que ya postularon a esa requisición o que tienen un período laboral activo.',
    },
    {
        numero: 3,
        titulo: 'Evaluación y puntuación',
        descripcion:
            'Si la requisición usa IA, la candidatura recibe automáticamente un porcentaje de match (skills %, requisitos %, total %). El equipo de RH agrega notas de entrevista y evaluación manual en el campo "notas".',
    },
    {
        numero: 4,
        titulo: 'Seleccionar candidato a contratar',
        descripcion:
            'Tras evaluar a los candidatos, RH elige al postulante a contratar. Desde la candidatura (o desde Períodos Laborales) se inicia el proceso de contratación.',
    },
];

const flujoContratacion: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Crear período laboral',
        descripcion:
            'Desde Períodos Laborales → Nuevo se vincula persona + puesto + requisición. Se define número de empleado, fecha de inicio, tipo de contrato, salario diario y sueldo mensual. El período nace en estado "activo".',
    },
    {
        numero: 2,
        titulo: 'Verificar datos completos',
        descripcion:
            'Antes de generar el contrato PDF, la persona debe tener todos los Datos Extra capturados (CURP obligatorio — el sistema extrae sexo, lugar de nacimiento y calcula edad desde el CURP) y al menos un contacto de emergencia.',
    },
    {
        numero: 3,
        titulo: 'Generar documentos PDF',
        descripcion:
            'El sistema genera automáticamente: el contrato laboral completo (con foto, datos personales y contactos de emergencia), el gafete del empleado (con QR) y la tarjeta de acceso (con QR). Se descargan desde el período laboral.',
    },
    {
        numero: 4,
        titulo: 'Registrar skills y requerimientos demostrados',
        descripcion:
            'Al contratar se registran los SkillDemostrada y RequerimientoDemostrado — evidencia de que la persona cumple lo definido en el puesto. Quedan ligados al período laboral para auditoría.',
    },
    {
        numero: 5,
        titulo: 'Baja del empleado (cuando aplique)',
        descripcion:
            'Cuando termina el contrato, la acción "terminar" cambia el estado a "baja" y registra fecha_fin. El período queda en el histórico de la persona y del puesto.',
    },
];

const flujoOnboarding: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Crear el onboarding',
        descripcion:
            'Desde el período laboral del empleado se crea un Onboarding. Nace con progreso 0% y fecha_inicio de hoy. Cada empleado tiene su propio onboarding vinculado a su período laboral.',
    },
    {
        numero: 2,
        titulo: 'Agregar tareas de onboarding',
        descripcion:
            'Se registran tareas (OnboardingTarea) con título, descripción, fecha de vencimiento y opcionalmente un responsable (otro empleado activo que actúa como mentor/capacitador).',
    },
    {
        numero: 3,
        titulo: 'Ejecutar y marcar tareas',
        descripcion:
            'A medida que el empleado completa las tareas, el responsable o RH las marca como completadas. Se puede adjuntar evidencia (archivo PDF/imagen) a cada tarea para auditoría.',
    },
    {
        numero: 4,
        titulo: 'Seguimiento de progreso',
        descripcion:
            'El sistema calcula automáticamente el progreso = (tareas completadas / tareas totales) × 100. RH puede visualizar qué empleados están atrasados en su onboarding desde el dashboard.',
    },
    {
        numero: 5,
        titulo: 'Cierre del onboarding',
        descripcion:
            'Cuando todas las tareas están completadas (100%), el onboarding se considera cerrado. La persona queda lista para su operación regular en el puesto.',
    },
];

function PasoFlujoCard({ numero, titulo, descripcion }: PasoFlujo) {
    return (
        <div className="flex gap-4 rounded-lg border border-base-300 bg-base-100 p-4">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary font-bold text-primary-content">
                {numero}
            </div>
            <div>
                <h3 className="mb-1 font-semibold">{titulo}</h3>
                <p className="text-sm text-base-content/70">{descripcion}</p>
            </div>
        </div>
    );
}

export default function DocumentacionRh() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentación - Recursos Humanos" />

            <div className="p-6">
                <div className="mb-6 flex items-center gap-3">
                    <BookOpenIcon className="size-7 text-primary" />
                    <div>
                        <h1 className="text-2xl font-semibold">Documentación - Módulo de Recursos Humanos</h1>
                        <p className="text-sm text-base-content/60">
                            Flujo completo desde la creación del puesto hasta las actividades de onboarding del nuevo empleado.
                        </p>
                    </div>
                </div>

                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Creación del Puesto" defaultChecked />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <BriefcaseIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Creación del Puesto</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            El puesto es la base del módulo: define qué se busca, qué habilidades y requerimientos pide, y qué actividades se realizarán.
                        </p>
                        <div className="space-y-3">
                            {flujoPuestos.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Requisición" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <ClipboardListIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Creación de Requisición</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            La requisición formaliza la solicitud de contratación. Vincula un puesto con las condiciones específicas de esta vacante.
                        </p>
                        <div className="space-y-3">
                            {flujoRequisicion.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Personas" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <UsersIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Creación de Personas y Datos Extra</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            Cada candidato/empleado es una "Persona" única en el sistema. Los "Datos Extra" son los datos extendidos necesarios para generar el contrato laboral.
                        </p>
                        <div className="space-y-3">
                            {flujoPersonas.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Postulación" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <UserPlusIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Añadir Postulación (Candidatura)</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            Una candidatura liga una persona a una requisición abierta. Aquí se evalúa el fit del candidato antes de contratar.
                        </p>
                        <div className="space-y-3">
                            {flujoPostulacion.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Contratación" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <FileSignatureIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Contratación (Período Laboral)</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            La contratación crea un PeriodoLaboral que vincula persona + puesto + requisición. De aquí salen el contrato, el gafete y la tarjeta de acceso.
                        </p>
                        <div className="space-y-3">
                            {flujoContratacion.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_rh_tabs" role="tab" className="tab" aria-label="Onboarding" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <CheckSquareIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Actividades de Onboarding</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            Cada nuevo empleado tiene un onboarding con tareas asignadas. El sistema calcula el progreso automáticamente y permite adjuntar evidencia por tarea.
                        </p>
                        <div className="space-y-3">
                            {flujoOnboarding.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
