import { CatalogoPanel, type CampoCatalogo, type FilaCatalogo } from '@/components/qal/catalogo-panel';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { QalLaboratorio, QalSoldador, QalTipoPieza, QalCatalogoSimple } from '@/types/models';
import { Head } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Catálogos', href: '/admin/calidad/catalogos' },
];

type Props = {
    soldadores: QalSoldador[];
    laboratorios: QalLaboratorio[];
    tiposPieza: QalTipoPieza[];
    equipos: QalCatalogoSimple[];
    operadores: QalCatalogoSimple[];
    responsables: QalCatalogoSimple[];
    supervisoresPintura: QalCatalogoSimple[];
    defectosSoldadura: QalCatalogoSimple[];
    defectosPintura: QalCatalogoSimple[];
};

/** El campo que casi todos los catálogos comparten. */
const SOLO_NOMBRE: CampoCatalogo[] = [{ k: 'nombre', label: 'Nombre', req: true, ancho: 'ancho' }];

type Definicion = {
    clave: string;
    /** Sufijo del permiso: `qal.<permiso>.ver` / `.crear` / `.editar`. */
    permiso: string;
    titulo: string;
    descripcion: string;
    campos: CampoCatalogo[];
    filas: FilaCatalogo[];
    ayuda?: ReactNode;
    aviso?: (fila: FilaCatalogo) => ReactNode;
};

/** Hoy es la fecha contra la que se juzga una certificación. */
const hoy = new Date().toISOString().slice(0, 10);

export default function CatalogosCalidad(props: Props) {
    const { can } = useCan();

    const definiciones: Definicion[] = [
        {
            clave: 'soldadores',
            permiso: 'soldadores',
            titulo: 'Soldadores',
            descripcion:
                'El padrón. La clave es la que se estampa en la pieza y la que enlaza al soldador con su WPQR en el dosier.',
            campos: [
                { k: 'nombre', label: 'Nombre completo', ph: 'ANGEL PEREZ CAÑETE', req: true, ancho: 'ancho' },
                { k: 'clave', label: 'Clave', ph: 'APC', mayusculas: true },
                { k: 'certificacion', label: 'Certificación', ph: 'WPQR-014', ancho: 'ancho' },
                { k: 'certificacion_vence_at', label: 'Vence el', tipo: 'date' },
            ],
            filas: props.soldadores as unknown as FilaCatalogo[],
            ayuda: (
                <>
                    La clave debe coincidir con la del WPQR que se suba a la biblioteca del dosier; si no, el dosier
                    avisará de que ese soldador no tiene certificado. Sin fecha de vencimiento nadie puede saber que
                    una pieza se soldó con una certificación caducada.
                </>
            ),
            aviso: (fila) => {
                const vence = fila.certificacion_vence_at as string | null;

                if (!vence) {
                    return null;
                }

                return vence < hoy ? (
                    <span className="badge badge-sm badge-error">certificación vencida</span>
                ) : null;
            },
        },
        {
            clave: 'laboratorios',
            permiso: 'laboratorios',
            titulo: 'Laboratorios de PND',
            descripcion: 'Los que firman los informes de ensayos no destructivos.',
            campos: [
                { k: 'nombre', label: 'Laboratorio', ph: 'PILOMEX', req: true, ancho: 'ancho' },
                { k: 'siglas', label: 'Siglas', ph: 'PLX', mayusculas: true },
            ],
            filas: props.laboratorios as unknown as FilaCatalogo[],
        },
        {
            clave: 'tipos-pieza',
            permiso: 'tipos-pieza',
            titulo: 'Tipos de pieza',
            descripcion:
                'El prefijo es el que usa ingeniería en la marca; con él la captura deduce sola el tipo.',
            campos: [
                { k: 'prefijo', label: 'Prefijo', ph: 'TP', req: true, mayusculas: true },
                { k: 'descripcion', label: 'Descripción', ph: 'Trabe principal', req: true, ancho: 'ancho' },
            ],
            filas: props.tiposPieza as unknown as FilaCatalogo[],
            ayuda: (
                <>
                    La marca viene como <span className="font-mono">OBRA-PREFIJO-CONSECUTIVO</span>, así que el
                    prefijo abre el segundo tramo: en <span className="font-mono">PIP-TP12-3</span>, el{' '}
                    <span className="font-mono">TP</span> rellena el tipo solo. Cámbialos únicamente de acuerdo con
                    ingeniería.
                </>
            ),
        },
        {
            clave: 'equipos',
            permiso: 'equipos',
            titulo: 'Equipos de 1ª',
            descripcion: 'Máquinas de corte y habilitado.',
            campos: [{ k: 'nombre', label: 'Equipo', ph: 'FICEP Gemini (placa)', req: true, ancho: 'ancho' }],
            filas: props.equipos as unknown as FilaCatalogo[],
        },
        {
            clave: 'operadores',
            permiso: 'operadores',
            titulo: 'Operadores de 1ª',
            descripcion: 'Quién opera la máquina en corte y habilitado.',
            campos: SOLO_NOMBRE,
            filas: props.operadores as unknown as FilaCatalogo[],
        },
        {
            clave: 'responsables',
            permiso: 'responsables',
            titulo: 'Responsables de módulo',
            descripcion: 'Se eligen en 2ª transformación.',
            campos: SOLO_NOMBRE,
            filas: props.responsables as unknown as FilaCatalogo[],
        },
        {
            clave: 'supervisores-pintura',
            permiso: 'supervisores-pintura',
            titulo: 'Supervisores de pintura',
            descripcion: 'Se eligen en 3ª transformación, en lugar del responsable de módulo.',
            campos: SOLO_NOMBRE,
            filas: props.supervisoresPintura as unknown as FilaCatalogo[],
        },
        {
            clave: 'defectos-soldadura',
            permiso: 'defectos-soldadura',
            titulo: 'Defectos de soldadura',
            descripcion: 'Los que puede marcar el inspector, con su contador.',
            campos: [{ k: 'nombre', label: 'Defecto', ph: 'Socavación', req: true, ancho: 'ancho' }],
            filas: props.defectosSoldadura as unknown as FilaCatalogo[],
            ayuda: (
                <>
                    Cuidado al renombrar uno: los registros ya guardados conservan el texto viejo, así que en los
                    reportes saldrán como dos defectos distintos.
                </>
            ),
        },
        {
            clave: 'defectos-pintura',
            permiso: 'defectos-pintura',
            titulo: 'Defectos de pintura',
            descripcion: 'Los que puede marcar el inspector de 3ª.',
            campos: [{ k: 'nombre', label: 'Defecto', ph: 'Falta pintura (FP)', req: true, ancho: 'ancho' }],
            filas: props.defectosPintura as unknown as FilaCatalogo[],
            ayuda: (
                <>Misma advertencia que en soldadura: renombrar uno no reescribe los registros ya guardados.</>
            ),
        },
    ];

    // Sólo se ofrecen las listas que el usuario puede consultar.
    const visibles = definiciones.filter((d) => can(`qal.${d.permiso}.ver`));
    const [actual, setActual] = useState(visibles[0]?.clave ?? '');
    const activa = visibles.find((d) => d.clave === actual) ?? visibles[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catálogos de Calidad" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Catálogos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Las listas que alimentan la captura de calidad. Lo que se cambia aquí lo ven todas las
                        pantallas del módulo.
                    </p>
                </div>

                {!activa ? (
                    <div className="alert">
                        <span>No tienes permiso para consultar ninguno de los catálogos de Calidad.</span>
                    </div>
                ) : (
                    <>
                        <div role="tablist" className="tabs tabs-boxed mb-5 flex-wrap">
                            {visibles.map((d) => (
                                <button
                                    key={d.clave}
                                    type="button"
                                    role="tab"
                                    className={`tab ${d.clave === activa.clave ? 'tab-active' : ''}`}
                                    onClick={() => setActual(d.clave)}
                                >
                                    {d.titulo}
                                    <span className="text-base-content/50 ml-1.5 text-xs">
                                        {d.filas.filter((f) => f.activo).length}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <CatalogoPanel
                            key={activa.clave}
                            clave={activa.clave}
                            descripcion={activa.descripcion}
                            campos={activa.campos}
                            filas={activa.filas}
                            puedeCrear={can(`qal.${activa.permiso}.crear`)}
                            puedeEditar={can(`qal.${activa.permiso}.editar`)}
                            ayuda={activa.ayuda}
                            aviso={activa.aviso}
                        />
                    </>
                )}

                <p className="text-base-content/60 mt-6 text-sm">
                    Ninguna lista tiene botón de borrar a propósito. <strong>Desactivar</strong> saca el valor de los
                    desplegables pero no toca los registros que ya lo mencionan; borrarlo dejaría reportes citando algo
                    que ya no existe.
                </p>
            </div>
        </AppLayout>
    );
}
