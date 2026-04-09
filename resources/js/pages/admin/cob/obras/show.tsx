import { calcularResumen } from '@/components/cob/calculos';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { EstimacionesGantt } from '@/components/cob/estimaciones-gantt';
import { formatearMXN } from '@/components/cob/money-display';
import { ResumenFinancieroCard } from '@/components/cob/resumen-financiero';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    COB_ADENDA_ESTADO_LABELS,
    COB_ADENDA_TIPO_LABELS,
    COB_ANTICIPO_ESTADO_LABELS,
    COB_COMPARATIVO_ESTADO_LABELS,
    COB_DISPUTA_ESTADO_LABELS,
    COB_TIPO_CONTRATO_LABELS,
    type Cliente,
    type Obra,
} from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

type Props = {
    obra: Obra;
    clientes: Pick<Cliente, 'id' | 'nombre'>[];
};

type TabKey = 'resumen' | 'partidas' | 'estimaciones' | 'anticipos' | 'adendas' | 'comparativos' | 'deducciones' | 'gantt' | 'disputas' | 'penalizaciones' | 'configuracion';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'partidas', label: 'Partidas' },
    { key: 'estimaciones', label: 'Estimaciones' },
    { key: 'anticipos', label: 'Anticipos' },
    { key: 'adendas', label: 'Adendas' },
    { key: 'comparativos', label: 'Comparativos' },
    { key: 'deducciones', label: 'Deducciones' },
    { key: 'gantt', label: 'Gantt' },
    { key: 'disputas', label: 'Disputas' },
    { key: 'penalizaciones', label: 'Penalizaciones' },
    { key: 'configuracion', label: 'Configuracion' },
];

export default function ObraShow({ obra, clientes }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('resumen');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
    ];

    const resumen = calcularResumen(
        obra.partidas ?? [],
        obra.estimaciones ?? [],
        obra.anticipos ?? [],
        obra.comparativos ?? [],
        obra.deducciones ?? [],
        obra.tipo_contrato ?? null,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Obra ${obra.no} - Cobranza`} />

            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{obra.no} - {obra.descripcion}</h1>
                        {obra.cliente && <p className="text-sm opacity-70">Cliente: {obra.cliente.nombre}</p>}
                    </div>
                    <a
                        href={`/admin/cob/obras/${obra.id}/estado-cuenta-pdf`}
                        target="_blank"
                        className="btn btn-outline btn-sm"
                    >
                        Estado de Cuenta PDF
                    </a>
                </div>

                {/* Tabs */}
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

                {/* Tab content */}
                {activeTab === 'resumen' && <ResumenTab obra={obra} clientes={clientes} resumen={resumen} />}
                {activeTab === 'partidas' && <PartidasTab obra={obra} />}
                {activeTab === 'estimaciones' && <EstimacionesTab obra={obra} />}
                {activeTab === 'anticipos' && <AnticiposTab obra={obra} />}
                {activeTab === 'adendas' && <AdendasTab obra={obra} />}
                {activeTab === 'comparativos' && <ComparativosTab obra={obra} />}
                {activeTab === 'deducciones' && <DeduccionesTab obra={obra} />}
                {activeTab === 'gantt' && <GanttTab obra={obra} />}
                {activeTab === 'disputas' && <DisputasTab obra={obra} />}
                {activeTab === 'penalizaciones' && <PenalizacionesTab obra={obra} />}
                {activeTab === 'configuracion' && <ConfiguracionTab obra={obra} />}
            </div>
        </AppLayout>
    );
}

// -- Resumen Tab --
function ResumenTab({ obra, clientes, resumen }: { obra: Obra; clientes: Pick<Cliente, 'id' | 'nombre'>[]; resumen: ReturnType<typeof calcularResumen> }) {
    const form = useForm({
        cliente_id: String(obra.cliente_id ?? ''),
        tipo_contrato: obra.tipo_contrato ?? '',
        monto: String(obra.monto ?? ''),
        monto_iva: String(obra.monto_iva ?? ''),
        anticipo: String(obra.anticipo ?? ''),
        garantia: String(obra.garantia ?? ''),
        peso: String(obra.peso ?? ''),
        porcentaje_fabricacion: String(obra.porcentaje_fabricacion ?? ''),
        porcentaje_montaje: String(obra.porcentaje_montaje ?? ''),
        porcentaje_otros: String(obra.porcentaje_otros ?? ''),
        descripcion_otros: obra.descripcion_otros ?? '',
        activa: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/obras/${obra.id}/financial`);
    };

    return (
        <div className="space-y-6">
            <ResumenFinancieroCard obra={obra} resumen={resumen} />

            <div className="card bg-base-100 border p-6">
                <h2 className="text-lg font-semibold mb-4">Datos Financieros</h2>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
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

                        <FormField label="Garantia" htmlFor="garantia" error={form.errors.garantia}>
                            <Input type="number" step="0.01" value={form.data.garantia} onChange={(e) => form.setData('garantia', e.target.value)} />
                        </FormField>

                        <FormField label="Peso (Ton)" htmlFor="peso" error={form.errors.peso}>
                            <Input type="number" step="0.01" value={form.data.peso} onChange={(e) => form.setData('peso', e.target.value)} />
                        </FormField>

                        <FormField label="% Fabricacion" htmlFor="porcentaje_fabricacion" error={form.errors.porcentaje_fabricacion}>
                            <Input type="number" step="0.01" max="100" value={form.data.porcentaje_fabricacion} onChange={(e) => form.setData('porcentaje_fabricacion', e.target.value)} />
                        </FormField>

                        <FormField label="% Montaje" htmlFor="porcentaje_montaje" error={form.errors.porcentaje_montaje}>
                            <Input type="number" step="0.01" max="100" value={form.data.porcentaje_montaje} onChange={(e) => form.setData('porcentaje_montaje', e.target.value)} />
                        </FormField>

                        <FormField label="% Otros" htmlFor="porcentaje_otros" error={form.errors.porcentaje_otros}>
                            <Input type="number" step="0.01" max="100" value={form.data.porcentaje_otros} onChange={(e) => form.setData('porcentaje_otros', e.target.value)} />
                        </FormField>

                        <FormField label="Descripcion Otros" htmlFor="descripcion_otros" error={form.errors.descripcion_otros}>
                            <Input value={form.data.descripcion_otros} onChange={(e) => form.setData('descripcion_otros', e.target.value)} />
                        </FormField>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Guardar Datos Financieros
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// -- Partidas Tab --
function PartidasTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Partidas</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/partidas/create`}><PlusIcon className="size-4" /> Nueva Partida</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descripcion</th>
                            <th className="text-right">Monto</th>
                            <th>Adicional</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(obra.partidas ?? []).map((p) => (
                            <tr key={p.id}>
                                <td className="capitalize">{p.tipo}</td>
                                <td>{p.descripcion}</td>
                                <td className="text-right">{formatearMXN(p.monto)}</td>
                                <td>{p.es_adicional ? 'Si' : 'No'}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/partidas/${p.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/partidas/${p.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.partidas ?? []).length === 0 && (
                            <tr><td colSpan={5} className="text-center opacity-50">No hay partidas</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Estimaciones Tab --
function EstimacionesTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Estimaciones</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/estimaciones/create`}><PlusIcon className="size-4" /> Nueva Estimacion</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Folio</th>
                            <th>Periodo</th>
                            <th className="text-right">Monto Estimado</th>
                            <th className="text-right">Monto Pagado</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(obra.estimaciones ?? []).map((e) => (
                            <tr key={e.id}>
                                <td>{e.numero_estimacion}</td>
                                <td>{e.folio ?? '-'}</td>
                                <td>{e.inicio && e.fin ? `${formatFecha(e.inicio)} - ${formatFecha(e.fin)}` : '-'}</td>
                                <td className="text-right">{formatearMXN(e.monto_estimado)}</td>
                                <td className="text-right">{formatearMXN(e.monto_pagado)}</td>
                                <td><EstadoBadge estado={e.estado} /></td>
                                <td>
                                    <a href={`/admin/cob/obras/${obra.id}/estimaciones/${e.id}/edit`} className="btn btn-ghost btn-xs">Editar</a>
                                </td>
                            </tr>
                        ))}
                        {(obra.estimaciones ?? []).length === 0 && (
                            <tr><td colSpan={7} className="text-center opacity-50">No hay estimaciones</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function formatFecha(fecha: string | null): string {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// -- Anticipos Tab --

function AnticiposTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Anticipos</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/anticipos/create`}><PlusIcon className="size-4" /> Nuevo Anticipo</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr><th>Folio</th><th>Fecha Emision</th><th className="text-right">Monto</th><th>Estado</th><th>Comprobante</th><th>Fecha Pagado</th><th></th></tr>
                    </thead>
                    <tbody>
                        {(obra.anticipos ?? []).map((a) => (
                            <tr key={a.id}>
                                <td>{a.folio ?? '-'}</td>
                                <td>{formatFecha(a.fecha_emision)}</td>
                                <td className="text-right">{formatearMXN(a.monto)}</td>
                                <td><span className="badge badge-sm">{COB_ANTICIPO_ESTADO_LABELS[a.estado] ?? a.estado}</span></td>
                                <td>{a.comprobante ? a.comprobante.split('/').pop() : '-'}</td>
                                <td>{formatFecha(a.fecha_pagado)}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/anticipos/${a.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    {a.estado === 'pendiente' && (
                                        <button className="btn btn-ghost btn-xs" onClick={() => router.post(`/admin/cob/obras/${obra.id}/anticipos/${a.id}/marcar-pagado`)}>Pagado</button>
                                    )}
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/anticipos/${a.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.anticipos ?? []).length === 0 && (
                            <tr><td colSpan={7} className="text-center opacity-50">No hay anticipos</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Adendas Tab --
function AdendasTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Adendas</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/adendas/create`}><PlusIcon className="size-4" /> Nueva Adenda</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Tipo</th><th>Descripcion</th><th className="text-right">Monto</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        {(obra.adendas ?? []).map((a) => (
                            <tr key={a.id}>
                                <td>{COB_ADENDA_TIPO_LABELS[a.tipo] ?? a.tipo}</td>
                                <td className="max-w-xs truncate">{a.descripcion}</td>
                                <td className="text-right">{formatearMXN(a.monto_modificacion)}</td>
                                <td><span className="badge badge-sm">{COB_ADENDA_ESTADO_LABELS[a.estado] ?? a.estado}</span></td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/adendas/${a.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/adendas/${a.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.adendas ?? []).length === 0 && (
                            <tr><td colSpan={5} className="text-center opacity-50">No hay adendas</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Comparativos Tab --
function ComparativosTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Comparativos</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/comparativos/create`}><PlusIcon className="size-4" /> Nuevo Comparativo</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Descripcion</th><th className="text-right">Monto Impacto</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        {(obra.comparativos ?? []).map((c) => (
                            <tr key={c.id}>
                                <td className="max-w-xs truncate">{c.descripcion}</td>
                                <td className="text-right">{formatearMXN(c.monto_impacto)}</td>
                                <td>{c.fecha_identificacion ?? '-'}</td>
                                <td><span className="badge badge-sm">{COB_COMPARATIVO_ESTADO_LABELS[c.estado] ?? c.estado}</span></td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/comparativos/${c.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/comparativos/${c.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.comparativos ?? []).length === 0 && (
                            <tr><td colSpan={5} className="text-center opacity-50">No hay comparativos</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Deducciones Tab --
function DeduccionesTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Deducciones</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/deducciones/create`}><PlusIcon className="size-4" /> Nueva Deduccion</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Descripcion</th><th className="text-right">Monto</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                        {(obra.deducciones ?? []).map((d) => (
                            <tr key={d.id}>
                                <td>{d.descripcion}</td>
                                <td className="text-right">{formatearMXN(d.monto)}</td>
                                <td>{d.fecha ?? '-'}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/deducciones/${d.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/deducciones/${d.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.deducciones ?? []).length === 0 && (
                            <tr><td colSpan={4} className="text-center opacity-50">No hay deducciones</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Disputas Tab --
function DisputasTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Disputas</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/disputas/create`}><PlusIcon className="size-4" /> Nueva Disputa</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Descripcion</th><th>Fecha Inicio</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
                    <tbody>
                        {(obra.disputas ?? []).map((d) => (
                            <tr key={d.id}>
                                <td className="max-w-xs truncate">{d.descripcion}</td>
                                <td>{d.fecha_inicio ?? '-'}</td>
                                <td><span className="badge badge-sm">{COB_DISPUTA_ESTADO_LABELS[d.estado] ?? d.estado}</span></td>
                                <td className="max-w-xs truncate">{d.resultado ?? '-'}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/disputas/${d.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/disputas/${d.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.disputas ?? []).length === 0 && (
                            <tr><td colSpan={5} className="text-center opacity-50">No hay disputas</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Penalizaciones Tab --
function PenalizacionesTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Penalizaciones</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/penalizaciones/create`}><PlusIcon className="size-4" /> Nueva Penalizacion</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Descripcion</th><th className="text-right">Monto</th><th>Tipo</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                        {(obra.penalizaciones ?? []).map((p) => (
                            <tr key={p.id}>
                                <td>{p.descripcion}</td>
                                <td className="text-right">{formatearMXN(p.monto)}</td>
                                <td>{p.tipo ?? '-'}</td>
                                <td>{p.fecha ?? '-'}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/penalizaciones/${p.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/penalizaciones/${p.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.penalizaciones ?? []).length === 0 && (
                            <tr><td colSpan={5} className="text-center opacity-50">No hay penalizaciones</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// -- Gantt Tab --
function GanttTab({ obra }: { obra: Obra }) {
    const [selectedYear, setSelectedYear] = useState<number | undefined>(undefined);

    const availableYears = useMemo(() => {
        const years = new Set<number>();
        for (const est of obra.estimaciones ?? []) {
            for (const h of est.historial ?? []) {
                years.add(new Date(h.fecha_cambio).getFullYear());
            }
        }
        return [...years].sort((a, b) => b - a);
    }, [obra]);

    return (
        <div className="flex flex-col gap-4">
            {availableYears.length > 0 && (
                <div className="flex justify-end">
                    <select
                        className="select select-bordered select-sm"
                        value={selectedYear ?? ''}
                        onChange={(e) => setSelectedYear(e.target.value ? Number(e.target.value) : undefined)}
                    >
                        <option value="">Todos los años</option>
                        {availableYears.map((y) => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
            )}
            <EstimacionesGantt obras={[obra]} year={selectedYear} />
        </div>
    );
}

// -- Configuracion Tab --
function ConfiguracionTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="flex justify-between mb-4">
                <h2 className="text-lg font-semibold">Configuracion de Documentos</h2>
                <Button size="sm" asChild>
                    <a href={`/admin/cob/obras/${obra.id}/configuracion-documentos/create`}><PlusIcon className="size-4" /> Nuevo Documento</a>
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead><tr><th>Nombre</th><th>Descripcion</th><th>Obligatorio</th><th></th></tr></thead>
                    <tbody>
                        {(obra.configuracion_documentos ?? []).map((d) => (
                            <tr key={d.id}>
                                <td>{d.nombre_documento}</td>
                                <td>{d.descripcion ?? '-'}</td>
                                <td>{d.obligatorio ? 'Si' : 'No'}</td>
                                <td className="flex gap-1">
                                    <a href={`/admin/cob/obras/${obra.id}/configuracion-documentos/${d.id}/edit`} className="btn btn-ghost btn-xs">
                                        <PencilIcon className="size-3" />
                                    </a>
                                    <button className="btn btn-ghost btn-xs text-error" onClick={() => router.delete(`/admin/cob/obras/${obra.id}/configuracion-documentos/${d.id}`)}>
                                        <Trash2Icon className="size-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {(obra.configuracion_documentos ?? []).length === 0 && (
                            <tr><td colSpan={4} className="text-center opacity-50">No hay documentos configurados</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
