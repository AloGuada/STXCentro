import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BookOpenIcon, FileTextIcon, SettingsIcon, StoreIcon, WalletIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Documentación', href: '/admin/documentacion/costos' },
    { title: 'Costos', href: '/admin/documentacion/costos' },
];

type Seccion = {
    titulo: string;
    descripcion: string;
};

const configuracionSecciones: Seccion[] = [
    {
        titulo: 'Proveedores',
        descripcion:
            'Registro de empresas a las que se les compran materiales o servicios. Cada proveedor tiene razón social, RFC, contacto, condiciones de crédito (días y si maneja crédito) y bancos. Son requeridos para crear órdenes de compra y solicitudes de pago.',
    },
    {
        titulo: 'Tipos de Centro de Costos',
        descripcion:
            'Categorías generales para clasificar los centros de costos (p. ej. "Material", "Mano de obra", "Equipo"). Facilitan el análisis presupuestal por tipo de gasto.',
    },
    {
        titulo: 'Centros de Costos',
        descripcion:
            'Conceptos específicos de gasto asociados a un tipo de centro de costos (p. ej. "Acero en láminas", "Cemento gris"). Cada centro de costos tiene un código único, descripción y ámbito (obras o planta). Los centros de costos se asignan a una obra (o al proyecto de planta) con su propio presupuesto.',
    },
    {
        titulo: 'Presupuestos',
        descripcion:
            'Asignación de centros de costos a una obra específica (o al proyecto de planta) con un monto presupuestado. Aquí se controla cuánto se ha acumulado (gastado) contra lo presupuestado. Las solicitudes de pago y órdenes de compra impactan directamente el acumulado del centro de costos.',
    },
    {
        titulo: 'Niveles de Aprobación',
        descripcion:
            'Define la cadena jerárquica de aprobadores por departamento. Cada nivel representa un rango (p. ej. "Control de Costos", "Gerente", "Director"). Al crear una solicitud, el sistema genera las aprobaciones según el departamento y los niveles configurados.',
    },
    {
        titulo: 'Mi Firma',
        descripcion:
            'Imagen de firma personal del usuario aprobador. Es requerida antes de poder acceder al módulo de aprobaciones. La firma aparece automáticamente en el PDF de la solicitud de pago al lado del nombre del aprobador.',
    },
];

type PasoFlujo = {
    numero: number;
    titulo: string;
    descripcion: string;
};

const flujoSolicitudesPago: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Creación de la solicitud',
        descripcion:
            'Cualquier usuario con permiso crea una solicitud de pago eligiendo departamento, proveedor, tipo de solicitud, conceptos a pagar (con centros de costos y montos) y archivos requeridos. La solicitud nace en estado "Pendiente Firma".',
    },
    {
        numero: 2,
        titulo: 'Generación del PDF y cadena de aprobación',
        descripcion:
            'Al crear la solicitud se genera automáticamente la cadena de aprobaciones según la configuración del departamento. El PDF muestra los aprobadores y sus firmas digitales.',
    },
    {
        numero: 3,
        titulo: 'Aprobaciones multinivel (secuenciales)',
        descripcion:
            'Cada aprobador ve la solicitud en "Mis Aprobaciones" cuando es su turno. Debe agregar observaciones y puede aprobar o rechazar. El sistema registra IP y hostname de cada aprobación para auditoría. Solo cuando el nivel anterior aprueba puede el siguiente continuar.',
    },
    {
        numero: 4,
        titulo: 'Aplicación presupuestal',
        descripcion:
            'Al completarse la última aprobación, la solicitud pasa a estado "Aprobada" y el monto se acumula en el centro de costos correspondiente de la obra. Si excede el presupuesto disponible, se marca como sobregiro pero se permite.',
    },
    {
        numero: 5,
        titulo: 'Confirmación por Costos',
        descripcion:
            'Un usuario con rol costos confirma la solicitud aprobada. Si la solicitud es de tipo "Contado" (transferencia, cheque, efectivo), al confirmar se crea automáticamente el Pago con estado "Programado" usando la fecha de pago solicitada.',
    },
    {
        numero: 6,
        titulo: 'Confirmación por Contabilidad (solo crédito)',
        descripcion:
            'Si la solicitud es de tipo "Crédito", después de la confirmación de costos, un usuario del rol contabilidad confirma. Al confirmar se crea automáticamente el Pago con estado "Programado".',
    },
];

const flujoPagos: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Creación del pago',
        descripcion:
            'Los pagos se crean automáticamente al confirmarse una solicitud (por costos para contado, por contabilidad para crédito) o al aceptarse una factura de orden de compra. Nacen en estado "Programado" con fecha ya definida.',
    },
    {
        numero: 2,
        titulo: 'Parcialidades (opcional, solo crédito)',
        descripcion:
            'Los pagos de crédito pueden dividirse en parcialidades desde la vista del pago. Cada parcialidad es un pago hijo con su propia fecha y monto. La suma debe coincidir con el monto total del pago padre. Las parcialidades pueden anidarse recursivamente.',
    },
    {
        numero: 3,
        titulo: 'Subir comprobante de pago',
        descripcion:
            'Al realizarse la transferencia/cheque, costos sube el comprobante desde la vista del pago. Se registra la fecha de pago realizada y opcionalmente notas. El pago pasa a estado "Pagado".',
    },
    {
        numero: 4,
        titulo: 'Cascada automática',
        descripcion:
            'Cuando todas las parcialidades hijas se marcan como pagadas, el pago padre se marca automáticamente como pagado, y a su vez la solicitud o factura origen se marca como pagada.',
    },
];

const flujoPortalProveedores: PasoFlujo[] = [
    {
        numero: 1,
        titulo: 'Acceso del proveedor',
        descripcion:
            'El proveedor inicia sesión en /portal con sus credenciales. Ve solo la información relacionada a su cuenta: órdenes de compra recibidas, facturas registradas y pagos programados.',
    },
    {
        numero: 2,
        titulo: 'Subida de factura',
        descripcion:
            'Al recibir una orden de compra completa (con todas las entregas), el proveedor sube su factura (PDF + XML) contra esa orden. La factura queda en estado "Pendiente de Aprobación".',
    },
    {
        numero: 3,
        titulo: 'Aprobación por Costos',
        descripcion:
            'Un usuario con rol costos revisa la factura contra las entregas y la orden de compra. Si todo está en orden, aprueba la factura. Pasa a estado "Pendiente de Pago".',
    },
    {
        numero: 4,
        titulo: 'Aceptación por Contabilidad',
        descripcion:
            'Contabilidad acepta la factura aprobada por costos. Al aceptar se crea automáticamente el pago con la fecha programada según los días de crédito del proveedor (ajustada al viernes siguiente). El proveedor recibe notificación por correo.',
    },
    {
        numero: 5,
        titulo: 'Consulta de pagos programados',
        descripcion:
            'El proveedor ve en su portal la fecha programada de cada pago. Cuando se realiza el pago y se sube el comprobante, el proveedor puede consultar y descargar el comprobante desde el portal.',
    },
];

function SeccionCard({ titulo, descripcion }: Seccion) {
    return (
        <div className="rounded-lg border border-base-300 bg-base-100 p-4">
            <h3 className="mb-2 font-semibold">{titulo}</h3>
            <p className="text-sm text-base-content/70">{descripcion}</p>
        </div>
    );
}

function PasoFlujoCard({ numero, titulo, descripcion }: PasoFlujo) {
    return (
        <div className="flex gap-4 rounded-lg border border-base-300 bg-base-100 p-4">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-content font-bold">
                {numero}
            </div>
            <div>
                <h3 className="mb-1 font-semibold">{titulo}</h3>
                <p className="text-sm text-base-content/70">{descripcion}</p>
            </div>
        </div>
    );
}

export default function DocumentacionCostos() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documentación - Costos" />

            <div className="p-6">
                <div className="mb-6 flex items-center gap-3">
                    <BookOpenIcon className="size-7 text-primary" />
                    <div>
                        <h1 className="text-2xl font-semibold">Documentación - Módulo de Costos</h1>
                        <p className="text-sm text-base-content/60">
                            Guía de configuración y flujos operativos del módulo de costos.
                        </p>
                    </div>
                </div>

                <div role="tablist" className="tabs tabs-bordered mb-6">
                    <input type="radio" name="doc_costos_tabs" role="tab" className="tab" aria-label="Configuración" defaultChecked />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <SettingsIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Configuración inicial</h2>
                        </div>
                        <p className="mb-4 text-sm text-base-content/70">
                            Antes de empezar a operar el módulo de costos, deben configurarse estos catálogos en el orden sugerido.
                        </p>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            {configuracionSecciones.map((s) => (
                                <SeccionCard key={s.titulo} {...s} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_costos_tabs" role="tab" className="tab" aria-label="Flujo Solicitudes de Pago" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <FileTextIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Flujo de Solicitudes de Pago</h2>
                        </div>
                        <div className="space-y-3">
                            {flujoSolicitudesPago.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_costos_tabs" role="tab" className="tab" aria-label="Flujo Pagos" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <WalletIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Flujo de Pagos</h2>
                        </div>
                        <div className="space-y-3">
                            {flujoPagos.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>

                    <input type="radio" name="doc_costos_tabs" role="tab" className="tab" aria-label="Flujo Portal Proveedores" />
                    <div role="tabpanel" className="tab-content py-4">
                        <div className="mb-4 flex items-center gap-2">
                            <StoreIcon className="size-5 text-base-content/60" />
                            <h2 className="text-lg font-medium">Flujo del Portal de Proveedores</h2>
                        </div>
                        <div className="space-y-3">
                            {flujoPortalProveedores.map((p) => (
                                <PasoFlujoCard key={p.numero} {...p} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
