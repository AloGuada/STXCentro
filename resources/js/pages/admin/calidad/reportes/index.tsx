/**
 * Reportes de Calidad — los formatos F-STX-* en PDF.
 *
 * Es `Reportes_Steelex.html` portado al mono con una diferencia de fondo: el
 * documento lo arma el servidor, que es donde vive la regla del dosier, y lo
 * que se previsualiza aquí es el mismo PDF que se descarga. No hay una copia en
 * HTML que pueda separarse de él.
 *
 * Los formatos van en dos grupos y el distintivo se ve siempre: uno «para el
 * dosier» se entrega al cliente; uno de «uso interno» no debe salir de la
 * planta. Al cambiar de formato, estatus y vista vuelven a los suyos: los del
 * dosier arrancan en su hoja final con sólo las liberadas; los internos en el
 * histórico con todas.
 */

import { Head, Link, router } from '@inertiajs/react';
import { DownloadIcon, ExternalLinkIcon, PenToolIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Reportes', href: '/admin/calidad/reportes' },
];

type Destino = 'dosier' | 'interno';

type FichaFormato = {
    clave: string;
    codigo: string;
    titulo: string;
    destino: Destino;
    fase: string;
    subetapa: string | null;
    /** El mapeo es de una pieza, no de un periodo. */
    usaPeriodo: boolean;
    vistaPorDefecto: string;
    estatusPorDefecto: string;
};

type Opcion = { valor: string; texto: string };

type Filtros = {
    formato: string;
    obra: string;
    periodo: string;
    fecha: string;
    semana: string;
    inspector: string;
    estatus: string;
    vista: string;
    pieza: string;
};

/** Lo que hay en la obra para la etapa del formato elegido. */
type Opciones = {
    total: number;
    fechas: string[];
    semanas: string[];
    inspectores: Opcion[];
    piezas: Opcion[];
};

type Props = {
    formatos: FichaFormato[];
    obras: { id: number; no: string | null; descripcion: string | null }[];
    filtros: Filtros;
    opciones: Opciones | null;
};

const PERIODOS: Opcion[] = [
    { valor: 'dia', texto: 'Por día' },
    { valor: 'semana', texto: 'Por semana' },
    { valor: 'todo', texto: 'Todo el proyecto' },
];

const ESTATUS: Opcion[] = [
    { valor: 'liberadas', texto: 'Sólo liberadas' },
    { valor: 'todas', texto: 'Todas' },
    { valor: 'rechazadas', texto: 'Sólo rechazadas' },
    { valor: 'pendientes', texto: 'Sólo pendientes' },
];

function vistas(destino: Destino): Opcion[] {
    return [
        { valor: 'final', texto: destino === 'dosier' ? 'Hoja del dosier · liberadas en A' : 'Estado final · 1 fila por pieza' },
        { valor: 'revision', texto: 'Hoja de revisión · con los defectos que tuvo' },
        { valor: 'historico', texto: 'Histórico · 1 fila por inspección' },
    ];
}

const fechaCorta = (fecha: string) => fecha.split('-').reverse().join('/');

/** Sólo lo que lleva valor: la URL es lo que se comparte y se recarga. */
function sinVacios(valores: Record<string, string>): Record<string, string> {
    return Object.fromEntries(Object.entries(valores).filter(([, v]) => v !== ''));
}

function urlDelPdf(formato: FichaFormato, f: Filtros): string {
    const parametros: Record<string, string> = formato.usaPeriodo
        ? {
              obra: f.obra,
              periodo: f.periodo,
              fecha: f.periodo === 'dia' ? f.fecha : '',
              semana: f.periodo === 'semana' ? f.semana : '',
              inspector: f.inspector,
              estatus: f.estatus,
              vista: f.vista,
          }
        : { obra: f.obra, pieza: f.pieza };

    return `/admin/calidad/reportes/${formato.clave}?${new URLSearchParams(sinVacios(parametros)).toString()}`;
}

export default function Reportes({ formatos, obras, filtros, opciones }: Props) {
    const formato = formatos.find((f) => f.clave === filtros.formato) ?? formatos[0];

    // Un periodo por día o por semana sin elegir toma el más reciente con datos.
    const efectivos: Filtros = {
        ...filtros,
        fecha: filtros.fecha || opciones?.fechas[0] || '',
        semana: filtros.semana || opciones?.semanas[0] || '',
    };

    const cambiar = (cambios: Partial<Filtros>) => {
        router.get('/admin/calidad/reportes', sinVacios({ ...filtros, ...cambios }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const elegirFormato = (clave: string) => {
        const nuevo = formatos.find((f) => f.clave === clave) ?? formato;

        cambiar({
            formato: nuevo.clave,
            estatus: nuevo.estatusPorDefecto,
            vista: nuevo.vistaPorDefecto,
            fecha: '',
            semana: '',
            inspector: '',
            pieza: '',
        });
    };

    const falta = (() => {
        if (!filtros.obra) {
            return 'Elige la obra: cada formato es de una obra.';
        }

        if (!formato.usaPeriodo) {
            if (filtros.pieza) {
                return null;
            }

            return opciones?.piezas.length
                ? 'Elige la pieza: el mapeo es de una pieza.'
                : 'Esta obra todavía no tiene piezas con juntas mapeadas.';
        }

        if (filtros.periodo === 'dia' && !efectivos.fecha) {
            return 'No hay días con inspecciones de esta etapa en la obra.';
        }

        if (filtros.periodo === 'semana' && !efectivos.semana) {
            return 'No hay semanas con inspecciones de esta etapa en la obra.';
        }

        return null;
    })();

    const url = falta ? null : urlDelPdf(formato, efectivos);
    const paraDosier = formato.destino === 'dosier';

    const campo = (etiqueta: string, hijo: ReactNode, ancho = '') => (
        <label className={`flex min-w-0 flex-col gap-1 ${ancho}`}>
            <span className="text-base-content/60 text-[11px] font-medium">{etiqueta}</span>
            {hijo}
        </label>
    );

    const lista = (etiqueta: string, valor: string, opcionesDeLista: Opcion[], alCambiar: (v: string) => void, todos?: string) =>
        campo(
            etiqueta,
            <Select className="select-sm" value={valor} onValueChange={alCambiar}>
                {todos !== undefined && <SelectItem value="">{todos}</SelectItem>}
                {opcionesDeLista.length === 0 && todos === undefined && <SelectItem value="">(sin datos)</SelectItem>}
                {opcionesDeLista.map((o) => (
                    <SelectItem key={o.valor} value={o.valor}>
                        {o.texto}
                    </SelectItem>
                ))}
            </Select>,
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reportes de Calidad" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Reportes</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Los formatos F-STX-* en PDF. Lo que se ve abajo es el mismo documento que se descarga, con las
                            firmas del orden que fija el catálogo Firmantes.
                        </p>
                    </div>
                    <Link href="/admin/mi-firma" className="btn btn-sm btn-ghost">
                        <PenToolIcon className="size-4" />
                        Mi firma
                    </Link>
                </div>

                <div className="rounded-box border-base-300 bg-base-100 border p-3">
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {campo(
                            'Formato',
                            <Select className="select-sm" value={formato.clave} onValueChange={elegirFormato}>
                                <optgroup label="Para el dosier · se entregan al cliente">
                                    {formatos
                                        .filter((f) => f.destino === 'dosier')
                                        .map((f) => (
                                            <SelectItem key={f.clave} value={f.clave}>
                                                {f.titulo} ({f.codigo})
                                            </SelectItem>
                                        ))}
                                </optgroup>
                                <optgroup label="Uso interno · NO enviar al cliente">
                                    {formatos
                                        .filter((f) => f.destino === 'interno')
                                        .map((f) => (
                                            <SelectItem key={f.clave} value={f.clave}>
                                                {f.titulo} ({f.codigo})
                                            </SelectItem>
                                        ))}
                                </optgroup>
                            </Select>,
                            'col-span-2 sm:col-span-2 lg:col-span-3',
                        )}
                        {campo(
                            'Obra',
                            <Select
                                className="select-sm"
                                value={filtros.obra}
                                placeholder="Elige la obra"
                                onValueChange={(obra) => cambiar({ obra, fecha: '', semana: '', inspector: '', pieza: '' })}
                            >
                                {obras.map((o) => (
                                    <SelectItem key={o.id} value={String(o.id)}>
                                        {[o.no, o.descripcion].filter(Boolean).join(' — ')}
                                    </SelectItem>
                                ))}
                            </Select>,
                            'col-span-2 sm:col-span-2 lg:col-span-3',
                        )}

                        {formato.usaPeriodo ? (
                            <>
                                {lista('Periodo', filtros.periodo, PERIODOS, (periodo) => cambiar({ periodo }))}
                                {filtros.periodo === 'dia' &&
                                    lista(
                                        'Día',
                                        efectivos.fecha,
                                        (opciones?.fechas ?? []).map((f) => ({ valor: f, texto: fechaCorta(f) })),
                                        (fecha) => cambiar({ fecha }),
                                    )}
                                {filtros.periodo === 'semana' &&
                                    lista(
                                        'Semana',
                                        efectivos.semana,
                                        (opciones?.semanas ?? []).map((s) => ({ valor: s, texto: s })),
                                        (semana) => cambiar({ semana }),
                                    )}
                                {lista('Inspector', filtros.inspector, opciones?.inspectores ?? [], (inspector) => cambiar({ inspector }), 'Todos')}
                                {lista('Estatus', filtros.estatus, ESTATUS, (estatus) => cambiar({ estatus }))}
                                {lista('Mostrar', filtros.vista, vistas(formato.destino), (vista) => cambiar({ vista }))}
                            </>
                        ) : (
                            <div className="col-span-2 sm:col-span-4 lg:col-span-6">
                                {campo(
                                    'Pieza',
                                    <Select
                                        className="select-sm"
                                        value={filtros.pieza}
                                        placeholder="Elige la pieza"
                                        onValueChange={(pieza) => cambiar({ pieza })}
                                    >
                                        {(opciones?.piezas ?? []).map((o) => (
                                            <SelectItem key={o.valor} value={o.valor}>
                                                {o.texto}
                                            </SelectItem>
                                        ))}
                                    </Select>,
                                )}
                            </div>
                        )}
                    </div>

                    <div className="border-base-300 mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-xs">
                        <span className="flex flex-wrap items-center gap-2">
                            <span className={`badge badge-sm font-bold ${paraDosier ? 'badge-success' : 'badge-warning'}`}>
                                {paraDosier ? 'PARA EL DOSIER' : 'USO INTERNO · NO ENVIAR AL CLIENTE'}
                            </span>
                            <span className="text-base-content/60">
                                {formato.codigo} · {formato.fase} transformación
                                {opciones && ` · ${opciones.total} inspecciones de esta etapa en la obra`}
                            </span>
                        </span>
                        {url && (
                            <span className="flex gap-2">
                                <a href={url} target="_blank" rel="noreferrer" className="btn btn-xs btn-ghost">
                                    <ExternalLinkIcon className="size-3.5" />
                                    Abrir en otra pestaña
                                </a>
                                <a href={`${url}&descargar=1`} className="btn btn-xs btn-primary">
                                    <DownloadIcon className="size-3.5" />
                                    Descargar PDF
                                </a>
                            </span>
                        )}
                    </div>
                </div>

                {url ? (
                    <iframe
                        key={url}
                        src={url}
                        title={`${formato.codigo} · ${formato.titulo}`}
                        className="rounded-box border-base-300 bg-base-200 h-[78vh] w-full border"
                    />
                ) : (
                    <div className="rounded-box border-base-300 text-base-content/60 border border-dashed p-10 text-center text-sm">
                        {falta}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
