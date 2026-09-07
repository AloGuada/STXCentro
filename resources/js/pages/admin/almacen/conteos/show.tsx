import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, PrinterIcon, TriangleAlertIcon } from 'lucide-react';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Renglon = {
    id: number;
    orden: number;
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    clasificacion: 'A' | 'B' | 'C' | null;
    ubicacion: string | null;
    cantidad_sistema: number | null;
    cantidad_contada: number | null;
};

type Conteo = {
    id: number;
    folio: string;
    origen: 'programado' | 'manual';
    origen_etiqueta: string;
    almacen: string | null;
    almacen_nombre: string | null;
    programa_id: number | null;
    fecha_programada: string;
    fecha_cierre: string | null;
    responsable: string | null;
    estatus: 'pendiente' | 'contando' | 'cerrado' | 'cancelado';
    estatus_etiqueta: string;
    vencido: boolean;
    ajuste_folio: string | null;
    observaciones: string | null;
    renglones: Renglon[];
};

type Props = { conteo: Conteo };

const ESTATUS_CLASE: Record<Conteo['estatus'], string> = {
    pendiente: 'badge-ghost',
    contando: 'badge-info',
    cerrado: 'badge-success',
    cancelado: 'badge-error',
};

const CLASE_ABC: Record<'A' | 'B' | 'C', string> = {
    A: 'badge-error',
    B: 'badge-warning',
    C: 'badge-ghost',
};

/**
 * La hoja de conteo: qué toca contar ese día y en qué orden.
 *
 * Desde aquí se imprime la lista para caminar el almacén. Va sin el saldo del
 * sistema: un número a la vista es una respuesta sugerida, y el conteo sirve
 * como control justamente porque quien cuenta no sabe cuánto debería haber.
 * La captura de lo contado y el cierre que genera el ajuste llegan en la
 * siguiente rebanada; los renglones ya tienen dónde guardarlo.
 */
export default function ConteoShow({ conteo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
        { title: conteo.folio, href: `/admin/almacen/conteos/${conteo.id}` },
    ];

    const cerrado = conteo.estatus === 'cerrado' || conteo.estatus === 'cancelado';
    const contados = conteo.renglones.filter((r) => r.cantidad_contada !== null).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Conteo ${conteo.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{conteo.folio}</h1>
                            <span className={`badge badge-sm ${ESTATUS_CLASE[conteo.estatus]}`}>
                                {conteo.estatus_etiqueta}
                            </span>
                            <span className="badge badge-sm badge-ghost">{conteo.origen_etiqueta}</span>
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Almacén {conteo.almacen}
                            {conteo.almacen_nombre ? ` — ${conteo.almacen_nombre}` : ''} · programado el{' '}
                            {conteo.fecha_programada}
                            {conteo.responsable ? ` · cuenta ${conteo.responsable}` : ''}
                            {conteo.vencido && <span className="text-error"> · vencido</span>}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink
                            href={conteo.programa_id ? `/admin/almacen/conteos?programa_id=${conteo.programa_id}` : '/admin/almacen/conteos'}
                            variant="outline"
                        >
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <a
                            href={`/admin/almacen/conteos/${conteo.id}/pdf`}
                            target="_blank"
                            rel="noopener"
                            className="btn btn-primary"
                        >
                            <PrinterIcon className="size-4" />
                            Imprimir hoja
                        </a>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="text-base-content/70 text-sm">
                        <strong>{conteo.renglones.length}</strong> artículos por contar
                        {contados > 0 && <span> · {contados} ya capturados</span>}
                    </div>
                    {!cerrado && (
                        <span className="text-base-content/50 text-xs">
                            La captura de lo contado se habilita en la siguiente etapa.
                        </span>
                    )}
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th className="w-12 text-right">#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th className="w-16">Clase</th>
                                <th>Ubicación</th>
                                <th className="w-32 text-right">Contado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {conteo.renglones.map((r) => (
                                <tr key={r.id} className="hover">
                                    <td className="text-base-content/50 text-right font-mono text-xs">{r.orden}</td>
                                    <td className="font-mono text-xs">{r.codigo}</td>
                                    <td>{r.descripcion}</td>
                                    <td>
                                        {r.clasificacion && (
                                            <span className={`badge badge-xs ${CLASE_ABC[r.clasificacion]}`}>
                                                {r.clasificacion}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-base-content/60 text-sm">
                                        {r.ubicacion ?? <span className="text-base-content/40">—</span>}
                                    </td>
                                    <td className="text-right font-mono">
                                        {r.cantidad_contada === null ? (
                                            <span className="text-base-content/30">—</span>
                                        ) : (
                                            <>
                                                {numero(r.cantidad_contada)}{' '}
                                                <span className="text-base-content/40 text-xs">{r.unidad}</span>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {conteo.ajuste_folio && (
                    <div className="alert alert-success mt-4">
                        <span>
                            Este conteo cerró con diferencias y generó el ajuste{' '}
                            <strong className="font-mono">{conteo.ajuste_folio}</strong>, que es el que movió el saldo.
                        </span>
                    </div>
                )}

                {conteo.vencido && (
                    <div className="alert alert-warning mt-4">
                        <TriangleAlertIcon className="size-4" />
                        <span>Esta hoja tenía fecha {conteo.fecha_programada} y sigue sin contarse.</span>
                    </div>
                )}

                <p className="text-base-content/60 mt-4 text-sm">
                    La hoja impresa no trae el saldo del sistema a propósito: se anota lo que se encontró, no lo que
                    debería haber. Las diferencias se corrigen con un ajuste, nunca en esta hoja.
                </p>
            </div>
        </AppLayout>
    );
}
