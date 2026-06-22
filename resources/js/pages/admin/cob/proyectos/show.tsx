import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, LockIcon, PencilIcon, PlusIcon, Trash2Icon, UnlockIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { calcularResumenProyecto } from '@/components/cob/calculos';
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
import { OBRA_ESTATUS_LABELS, type Cliente, type Obra, type Proyecto } from '@/types/models';

type Props = {
    proyecto: Proyecto;
    clientes: Pick<Cliente, 'id' | 'nombre'>[];
};

type TabKey = 'resumen' | 'obras' | 'gantt' | 'datos';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'obras', label: 'Obras' },
    { key: 'gantt', label: 'Gantt' },
    { key: 'datos', label: 'Datos del proyecto' },
];

export default function ProyectoShow({ proyecto, clientes }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('resumen');
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

                {activeTab === 'resumen' && <ResumenTab proyecto={proyecto} resumen={d} />}
                {activeTab === 'obras' && <ObrasTab proyecto={proyecto} />}
                {activeTab === 'gantt' && <PlaneacionGantt proyecto={proyecto} />}
                {activeTab === 'datos' && <DatosTab proyecto={proyecto} clientes={clientes} />}
            </div>
        </AppLayout>
    );
}

// -- Resumen (rollup del proyecto, misma tarjeta que la obra pero global) --
function ResumenTab({ proyecto, resumen }: { proyecto: Proyecto; resumen: ReturnType<typeof calcularResumenProyecto> }) {
    const obras = proyecto.obras ?? [];

    // Avance global: promedio de % de avance ponderado por el monto de partidas
    // de cada obra (las obras sin partidas no inclinan el promedio).
    const pesoTotal = obras.reduce((s, o) => s + (o.partidas ?? []).reduce((ss, p) => ss + Number(p.monto), 0), 0);
    const avance = pesoTotal > 0
        ? obras.reduce((s, o) => {
              const peso = (o.partidas ?? []).reduce((ss, p) => ss + Number(p.monto), 0);
              return s + Number(o.porcentaje_obra ?? 0) * peso;
          }, 0) / pesoTotal
        : 0;

    return (
        <div className="space-y-6">
            <ResumenFinancieroCard
                titulo={proyecto.descripcion}
                subtitulo={`${obras.length} ${obras.length === 1 ? 'obra' : 'obras'}`}
                resumen={resumen}
                anticipoMonto={resumen.totalAnticiposFacturados}
                anticipoLabel="Anticipo"
                avance={avance}
                avanceLabel="Avance del Proyecto"
            />

            <div>
                <h2 className="mb-3 text-lg font-semibold">Cronograma</h2>
                <PlaneacionGantt proyecto={proyecto} />
            </div>
        </div>
    );
}

// -- Obras del proyecto (tarjetas con enlace a cada obra) --
function ObrasTab({ proyecto }: { proyecto: Proyecto }) {
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
                <h2 className="text-lg font-semibold">Obras del proyecto</h2>
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
                    <Link href={`/admin/cob/obras/${obra.id}`} className="link link-hover font-semibold">
                        {obra.no} — {obra.descripcion}
                    </Link>
                    <span className={`badge badge-sm ${esAdicional ? 'badge-warning' : 'badge-ghost'}`}>
                        {esAdicional ? 'Adicional' : 'Obra'}
                    </span>
                    <span className={`badge badge-sm ${obra.estatus === 'cerrada' ? 'badge-error' : 'badge-success'}`}>
                        {OBRA_ESTATUS_LABELS[obra.estatus]}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <Link href={`/admin/cob/obras/${obra.id}`} className="btn btn-primary btn-sm gap-1 flex-nowrap">
                        Ver obra
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
                <p className="text-base-content/50 px-4 py-6 text-center text-sm">
                    Sin partidas. Agrégalas desde la página de la obra.
                </p>
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

// -- Datos del proyecto (paraguas: descripción + cliente) --
function DatosTab({ proyecto, clientes }: { proyecto: Proyecto; clientes: Pick<Cliente, 'id' | 'nombre'>[] }) {
    const form = useForm({
        descripcion: proyecto.descripcion ?? '',
        cliente_id: String(proyecto.cliente_id ?? ''),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/proyectos/${proyecto.id}`, { preserveScroll: true });
    };

    return (
        <div className="card bg-base-100 border p-6">
            <h2 className="mb-4 text-lg font-semibold">Datos del proyecto</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                </div>

                <p className="text-base-content/60 text-sm">
                    El contrato y los datos financieros se gestionan en cada obra del proyecto.
                </p>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                        Guardar
                    </Button>
                </div>
            </form>
        </div>
    );
}
