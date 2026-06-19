import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, LockIcon, PencilIcon, PlusIcon, Trash2Icon, UnlockIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import ArchivoViewerModal from '@/components/cob/archivo-viewer-modal';
import { calcularResumenProyecto } from '@/components/cob/calculos';
import {
    AdendasTab,
    AnticiposTab,
    ComparativosTab,
    ConfiguracionTab,
    DeduccionesTab,
    DisputasTab,
    DocumentacionTab,
    PenalizacionesTab,
} from '@/components/cob/comercial-tabs';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import { PlaneacionGantt } from '@/components/cob/planeacion-gantt';
import { ResumenFinancieroCard } from '@/components/cob/resumen-financiero';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    COB_TIPO_CONTRATO_LABELS,
    OBRA_ESTATUS_LABELS,
    type Cliente,
    type CobDocumentoArchivo,
    type CobDocumentoSeccion,
    type Obra,
    type Proyecto,
} from '@/types/models';

type Props = {
    proyecto: Proyecto;
    clientes: Pick<Cliente, 'id' | 'nombre'>[];
    documentoSecciones: CobDocumentoSeccion[];
};

type TabKey =
    | 'resumen'
    | 'partidas'
    | 'estimaciones'
    | 'anticipos'
    | 'adendas'
    | 'comparativos'
    | 'deducciones'
    | 'disputas'
    | 'penalizaciones'
    | 'documentacion'
    | 'configuracion'
    | 'gantt'
    | 'comerciales';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'partidas', label: 'Partidas' },
    { key: 'estimaciones', label: 'Estimaciones' },
    { key: 'anticipos', label: 'Anticipos' },
    { key: 'adendas', label: 'Adendas' },
    { key: 'comparativos', label: 'Comparativos' },
    { key: 'deducciones', label: 'Deducciones' },
    { key: 'disputas', label: 'Disputas' },
    { key: 'penalizaciones', label: 'Penalizaciones' },
    { key: 'documentacion', label: 'Documentación' },
    { key: 'configuracion', label: 'Configuración' },
    { key: 'gantt', label: 'Gantt' },
    { key: 'comerciales', label: 'Datos comerciales' },
];

export default function ProyectoShow({ proyecto, clientes, documentoSecciones }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('resumen');
    const [viewerArchivo, setViewerArchivo] = useState<CobDocumentoArchivo | null>(null);

    const obraBase = proyecto.obra_base ?? (proyecto.obras ?? []).find((o) => o.tipo !== 'adicional') ?? null;
    const d = calcularResumenProyecto(proyecto);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: 'Proyectos', href: '/admin/cob/proyectos' },
        { title: proyecto.descripcion, href: `/admin/cob/proyectos/${proyecto.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${proyecto.no} - Proyecto`} />

            <div className="p-6">
                <div className="mb-4">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        {proyecto.descripcion}
                        <span className={`badge ${proyecto.estatus === 'cerrada' ? 'badge-error' : 'badge-success'}`}>
                            {OBRA_ESTATUS_LABELS[proyecto.estatus]}
                        </span>
                    </h1>
                    <p className="text-base-content/60 text-sm">
                        {proyecto.no} · {proyecto.cliente?.nombre ?? 'Sin cliente'}
                    </p>
                </div>

                <div className="tabs tabs-bordered mb-6">
                    {TABS.map((tab) => (
                        <button
                            key={tab.key}
                            className={`tab ${activeTab === tab.key ? 'tab-active' : ''}`}
                            onClick={() => setActiveTab(tab.key)}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {activeTab === 'resumen' && <ResumenTab proyecto={proyecto} obraBase={obraBase} resumen={d} />}
                {activeTab === 'partidas' && <PartidasTab proyecto={proyecto} />}
                {activeTab === 'estimaciones' && <EstimacionesTab proyecto={proyecto} />}
                {obraBase && activeTab === 'anticipos' && <AnticiposTab obra={obraBase} />}
                {obraBase && activeTab === 'adendas' && <AdendasTab obra={obraBase} />}
                {obraBase && activeTab === 'comparativos' && <ComparativosTab obra={obraBase} />}
                {obraBase && activeTab === 'deducciones' && <DeduccionesTab obra={obraBase} />}
                {obraBase && activeTab === 'disputas' && <DisputasTab obra={obraBase} />}
                {obraBase && activeTab === 'penalizaciones' && <PenalizacionesTab obra={obraBase} />}
                {obraBase && activeTab === 'documentacion' && (
                    <DocumentacionTab obra={obraBase} documentoSecciones={documentoSecciones} onOpenArchivo={setViewerArchivo} />
                )}
                {obraBase && activeTab === 'configuracion' && <ConfiguracionTab obra={obraBase} />}
                {activeTab === 'gantt' && <PlaneacionGantt proyecto={proyecto} />}
                {activeTab === 'comerciales' && <DatosComercialesTab proyecto={proyecto} clientes={clientes} />}
            </div>

            {viewerArchivo && (
                <ArchivoViewerModal
                    nombre={viewerArchivo.nombre_original}
                    mime={viewerArchivo.mime}
                    streamUrl={`/admin/cob/documentos/archivos/${viewerArchivo.id}/stream`}
                    downloadUrl={`/admin/cob/documentos/archivos/${viewerArchivo.id}/descargar`}
                    onClose={() => setViewerArchivo(null)}
                />
            )}
        </AppLayout>
    );
}

// -- Resumen --
function ResumenTab({
    proyecto,
    obraBase,
    resumen,
}: {
    proyecto: Proyecto;
    obraBase: Obra | null;
    resumen: ReturnType<typeof calcularResumenProyecto>;
}) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {[
                    ['Contrato', proyecto.tipo_contrato ? (COB_TIPO_CONTRATO_LABELS[proyecto.tipo_contrato] ?? proyecto.tipo_contrato) : '-'],
                    ['Monto', proyecto.monto != null ? formatearMXN(Number(proyecto.monto)) : '-'],
                    ['Anticipo', proyecto.anticipo != null ? formatearMXN(Number(proyecto.anticipo)) : '-'],
                    ['Garantía', proyecto.garantia != null ? formatearMXN(Number(proyecto.garantia)) : '-'],
                ].map(([label, valor]) => (
                    <div key={label} className="rounded-box border border-base-300 p-3">
                        <div className="text-base-content/60 text-xs">{label}</div>
                        <div className="font-medium">{valor}</div>
                    </div>
                ))}
            </div>

            {obraBase && <ResumenFinancieroCard obra={obraBase} resumen={resumen} />}
        </div>
    );
}

// -- Partidas (obras del proyecto) --
function PartidasTab({ proyecto }: { proyecto: Proyecto }) {
    const { can } = useCan();
    const obras = proyecto.obras ?? [];

    const cambiarEstadoObra = (obra: Obra) => {
        const cerrar = obra.estatus === 'abierta';
        if (!window.confirm(`¿Seguro que deseas ${cerrar ? 'cerrar' : 'reabrir'} la obra ${obra.no}?`)) return;
        router.put(`/admin/cob/obras/${obra.id}/estado`, { estatus: cerrar ? 'cerrada' : 'abierta' }, { preserveScroll: true });
    };

    const totalProyecto = obras.reduce(
        (s, o) => s + (o.partidas ?? []).reduce((ss, p) => ss + Number(p.monto), 0),
        0,
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">Obras y partidas</h2>
                <Link href={`/admin/cob/proyectos/${proyecto.id}/obras/create`} className="btn btn-primary btn-sm gap-1 flex-nowrap">
                    <PlusIcon className="size-4" /> Nueva obra
                </Link>
            </div>

            {obras.length === 0 && (
                <p className="text-base-content/60 rounded-box border border-dashed border-base-300 p-6 text-center">
                    Este proyecto no tiene obras. Crea la primera con "Nueva obra".
                </p>
            )}

            {obras.map((obra) => (
                <ObraCard key={obra.id} proyecto={proyecto} obra={obra} can={can} onToggleEstado={cambiarEstadoObra} />
            ))}

            {obras.length > 0 && (
                <div className="rounded-box border border-base-300 bg-base-200 flex items-center justify-between px-4 py-3 font-bold">
                    <span>Total del proyecto</span>
                    <span>{formatearMXN(totalProyecto)}</span>
                </div>
            )}
        </div>
    );
}

function ObraCard({
    proyecto,
    obra,
    can,
    onToggleEstado,
}: {
    proyecto: Proyecto;
    obra: Obra;
    can: (permiso: string) => boolean;
    onToggleEstado: (obra: Obra) => void;
}) {
    const partidas = obra.partidas ?? [];
    const subtotal = partidas.reduce((s, p) => s + Number(p.monto), 0);
    const esAdicional = obra.tipo === 'adicional';

    return (
        <div className="rounded-box overflow-hidden border border-base-300">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 bg-base-200 px-4 py-2">
                <div className="flex items-center gap-2">
                    <span className="font-semibold">{obra.no} — {obra.descripcion}</span>
                    <span className={`badge badge-sm ${esAdicional ? 'badge-warning' : 'badge-ghost'}`}>
                        {esAdicional ? 'Adicional' : 'Obra'}
                    </span>
                    <span className={`badge badge-sm ${obra.estatus === 'cerrada' ? 'badge-error' : 'badge-success'}`}>
                        {OBRA_ESTATUS_LABELS[obra.estatus]}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <Link href={`/admin/cob/obras/${obra.id}/partidas/create`} className="btn btn-primary btn-sm gap-1 flex-nowrap">
                        <PlusIcon className="size-3" /> Partida
                    </Link>
                    <Link href={`/admin/cob/proyectos/${proyecto.id}/obras/${obra.id}/edit`} className="btn btn-outline btn-sm gap-1">
                        <PencilIcon className="size-3" /> Editar
                    </Link>
                    <a href={`/admin/costos/presupuestos/${obra.id}/edit`} className="btn btn-outline btn-sm">Presupuesto</a>
                    {can('cob.obras.cerrar') && (
                        <button
                            type="button"
                            className={`btn btn-outline btn-sm gap-1 ${obra.estatus === 'abierta' ? 'btn-error' : 'btn-success'}`}
                            onClick={() => onToggleEstado(obra)}
                        >
                            {obra.estatus === 'abierta' ? <LockIcon className="size-4" /> : <UnlockIcon className="size-4" />}
                            {obra.estatus === 'abierta' ? 'Cerrar' : 'Reabrir'}
                        </button>
                    )}
                </div>
            </div>

            {partidas.length === 0 ? (
                <p className="text-base-content/50 px-4 py-6 text-center text-sm">Sin partidas. Agrégalas con "Partida".</p>
            ) : (
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th className="text-right">Monto</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {partidas.map((p) => (
                            <tr key={p.id} className="hover">
                                <td className="capitalize">{p.tipo}</td>
                                <td>{p.descripcion}</td>
                                <td className="text-right">{formatearMXN(p.monto)}</td>
                                <td className="text-right">
                                    <div className="join">
                                        <Link
                                            href={`/admin/cob/obras/${obra.id}/partidas/${p.id}/edit`}
                                            className="btn btn-outline btn-xs join-item gap-1"
                                        >
                                            <PencilIcon className="size-3" /> Editar
                                        </Link>
                                        <button
                                            className="btn btn-outline btn-error btn-xs join-item gap-1"
                                            onClick={() => router.delete(`/admin/cob/obras/${obra.id}/partidas/${p.id}`, { preserveScroll: true })}
                                        >
                                            <Trash2Icon className="size-3" /> Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-semibold">
                            <td colSpan={2} className="text-right">Subtotal</td>
                            <td className="text-right">{formatearMXN(subtotal)}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            )}
        </div>
    );
}

// -- Estimaciones --
function EstimacionesTab({ proyecto }: { proyecto: Proyecto }) {
    return (
        <div className="rounded-box border border-base-300">
            <div className="flex items-center justify-between border-b border-base-300 px-4 py-2">
                <span className="font-semibold">Estimaciones del proyecto</span>
                <a href={`/admin/cob/proyectos/${proyecto.id}/estimaciones/create`} className="btn btn-primary btn-xs">
                    Nueva estimación
                </a>
            </div>
            <table className="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Folio</th>
                        <th>Periodo</th>
                        <th className="text-right">Monto estimado</th>
                        <th className="text-right">Pagado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {(proyecto.estimaciones ?? []).map((e) => (
                        <tr key={e.id}>
                            <td>{e.numero_estimacion}</td>
                            <td>{e.folio ?? '-'}</td>
                            <td>{e.inicio && e.fin ? `${fmtFecha(e.inicio)} - ${fmtFecha(e.fin)}` : '-'}</td>
                            <td className="text-right">{formatearMXN(Number(e.monto_estimado))}</td>
                            <td className="text-right">{formatearMXN(Number(e.monto_pagado))}</td>
                            <td><EstadoBadge estado={e.estado} /></td>
                            <td>
                                <a href={`/admin/cob/proyectos/${proyecto.id}/estimaciones/${e.id}/edit`} className="btn btn-ghost btn-xs">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    ))}
                    {(proyecto.estimaciones ?? []).length === 0 && (
                        <tr><td colSpan={7} className="py-6 text-center opacity-50">No hay estimaciones</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

// -- Datos comerciales (edita el proyecto) --
function DatosComercialesTab({ proyecto, clientes }: { proyecto: Proyecto; clientes: Pick<Cliente, 'id' | 'nombre'>[] }) {
    const form = useForm({
        descripcion: proyecto.descripcion ?? '',
        cliente_id: String(proyecto.cliente_id ?? ''),
        tipo_contrato: proyecto.tipo_contrato ?? '',
        monto: String(proyecto.monto ?? ''),
        monto_iva: String(proyecto.monto_iva ?? ''),
        anticipo: String(proyecto.anticipo ?? ''),
        garantia: String(proyecto.garantia ?? ''),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/proyectos/${proyecto.id}`, { preserveScroll: true });
    };

    return (
        <div className="card bg-base-100 border p-6">
            <h2 className="mb-4 text-lg font-semibold">Datos comerciales del proyecto</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <FormField label="Descripción" htmlFor="descripcion" error={form.errors.descripcion}>
                        <Input value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                    </FormField>

                    <FormField label="Cliente" htmlFor="cliente_id" error={form.errors.cliente_id}>
                        <Select value={form.data.cliente_id} onValueChange={(v) => form.setData('cliente_id', v)}>
                            <SelectTrigger><SelectValue placeholder="Seleccionar cliente" /></SelectTrigger>
                            <SelectContent>
                                {clientes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Tipo Contrato" htmlFor="tipo_contrato" error={form.errors.tipo_contrato}>
                        <Select value={form.data.tipo_contrato} onValueChange={(v) => form.setData('tipo_contrato', v)}>
                            <SelectTrigger><SelectValue placeholder="Seleccionar tipo" /></SelectTrigger>
                            <SelectContent>
                                {Object.entries(COB_TIPO_CONTRATO_LABELS).map(([k, v]) => (
                                    <SelectItem key={k} value={k}>{v}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField label="Monto" htmlFor="monto" error={form.errors.monto}>
                        <Input type="number" step="0.01" value={form.data.monto} onChange={(e) => form.setData('monto', e.target.value)} />
                    </FormField>

                    <FormField label="Monto IVA" htmlFor="monto_iva" error={form.errors.monto_iva}>
                        <Input type="number" step="0.01" value={form.data.monto_iva} onChange={(e) => form.setData('monto_iva', e.target.value)} />
                    </FormField>

                    <FormField label="Anticipo" htmlFor="anticipo" error={form.errors.anticipo}>
                        <Input type="number" step="0.01" value={form.data.anticipo} onChange={(e) => form.setData('anticipo', e.target.value)} />
                    </FormField>

                    <FormField label="Garantía" htmlFor="garantia" error={form.errors.garantia}>
                        <Input type="number" step="0.01" value={form.data.garantia} onChange={(e) => form.setData('garantia', e.target.value)} />
                    </FormField>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                        Guardar datos comerciales
                    </Button>
                </div>
            </form>
        </div>
    );
}

function fmtFecha(fecha: string | null): string {
    if (!fecha) return '-';
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
