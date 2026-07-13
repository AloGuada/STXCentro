import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { calcularDatosProyecto } from '@/components/cob/calculos';
import {
    AdendasTab,
    AnticiposTab,
    DeduccionesTab,
    DisputasTab,
    PenalizacionesTab,
} from '@/components/cob/comercial-tabs';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import { ResumenFinancieroCard } from '@/components/cob/resumen-financiero';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    COB_TIPO_CONTRATO_LABELS,
    OBRA_ESTATUS_LABELS,
    type Cliente,
    type Obra,
} from '@/types/models';

type Props = {
    obra: Obra;
    clientes: Pick<Cliente, 'id' | 'nombre'>[];
};

type TabKey =
    | 'resumen'
    | 'contrato'
    | 'partidas'
    | 'estimaciones'
    | 'anticipos'
    | 'adendas'
    | 'deducciones'
    | 'disputas'
    | 'penalizaciones';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'contrato', label: 'Contrato' },
    { key: 'partidas', label: 'Partidas' },
    { key: 'estimaciones', label: 'Estimaciones' },
    { key: 'anticipos', label: 'Anticipos' },
    { key: 'adendas', label: 'Adendas' },
    { key: 'deducciones', label: 'Deducciones' },
    { key: 'disputas', label: 'Disputas' },
    { key: 'penalizaciones', label: 'Penalizaciones' },
];

export default function ObraShow({ obra, clientes }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('resumen');

    const datos = calcularDatosProyecto(obra);
    const esAdicional = obra.tipo === 'adicional';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        ...(obra.proyecto
            ? [{ title: obra.proyecto.descripcion, href: `/admin/cob/proyectos/${obra.proyecto.id}` }]
            : []),
        { title: obra.descripcion, href: `/admin/cob/obras/${obra.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${obra.no} - Obra`} />

            <div className="p-6">
                <div className="mb-4">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        {obra.descripcion}
                        <span className={`badge badge-sm ${esAdicional ? 'badge-warning' : 'badge-ghost'}`}>
                            {esAdicional ? 'Adicional' : 'Obra'}
                        </span>
                        <span className={`badge ${obra.estatus === 'cerrada' ? 'badge-error' : 'badge-success'}`}>
                            {OBRA_ESTATUS_LABELS[obra.estatus]}
                        </span>
                    </h1>
                    <p className="text-base-content/60 text-sm">
                        {obra.no} · {obra.cliente?.nombre ?? 'Sin cliente'}
                        {obra.proyecto && (
                            <>
                                {' · '}
                                <Link href={`/admin/cob/proyectos/${obra.proyecto.id}`} className="link link-hover">
                                    Proyecto {obra.proyecto.no}
                                </Link>
                            </>
                        )}
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

                {activeTab === 'resumen' && (
                    <ResumenFinancieroCard
                        titulo={obra.descripcion}
                        subtitulo={`OP: ${obra.no}`}
                        resumen={datos}
                        anticipoMonto={datos.presupuestoEjecutar * (Number(obra.anticipo ?? 0) / 100)}
                        anticipoLabel={`Anticipo (${Number(obra.anticipo ?? 0).toFixed(4)}%)`}
                        avance={Number(obra.porcentaje_obra ?? 0)}
                        avanceLabel="Avance de Obra"
                    />
                )}
                {activeTab === 'contrato' && <ContratoTab obra={obra} clientes={clientes} />}
                {activeTab === 'partidas' && <PartidasTab obra={obra} />}
                {activeTab === 'estimaciones' && <EstimacionesTab obra={obra} />}
                {activeTab === 'anticipos' && <AnticiposTab obra={obra} />}
                {activeTab === 'adendas' && <AdendasTab obra={obra} />}
                {activeTab === 'deducciones' && <DeduccionesTab obra={obra} />}
                {activeTab === 'disputas' && <DisputasTab obra={obra} />}
                {activeTab === 'penalizaciones' && <PenalizacionesTab obra={obra} />}
            </div>
        </AppLayout>
    );
}

// -- Contrato / datos financieros de la obra --
function ContratoTab({ obra, clientes }: { obra: Obra; clientes: Pick<Cliente, 'id' | 'nombre'>[] }) {
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
        porcentaje_obra: String(obra.porcentaje_obra ?? ''),
        activa: obra.activa ?? true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/obras/${obra.id}/financial`, { preserveScroll: true });
    };

    return (
        <div className="card bg-base-100 border p-6">
            <h2 className="mb-4 text-lg font-semibold">Contrato y datos financieros</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <FormField label="Cliente" htmlFor="cliente_id" error={form.errors.cliente_id}>
                        <Select value={form.data.cliente_id} onValueChange={(v) => form.setData('cliente_id', v)} placeholder="Seleccionar cliente">
                            {clientes.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label="Tipo Contrato" htmlFor="tipo_contrato" error={form.errors.tipo_contrato}>
                        <Select value={form.data.tipo_contrato} onValueChange={(v) => form.setData('tipo_contrato', v)} placeholder="Seleccionar tipo">
                            {Object.entries(COB_TIPO_CONTRATO_LABELS).map(([k, v]) => (
                                <SelectItem key={k} value={k}>{v}</SelectItem>
                            ))}
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
                    <FormField label="Peso (ton)" htmlFor="peso" error={form.errors.peso}>
                        <Input type="number" step="0.01" value={form.data.peso} onChange={(e) => form.setData('peso', e.target.value)} />
                    </FormField>
                    <FormField label="% Fabricación" htmlFor="porcentaje_fabricacion" error={form.errors.porcentaje_fabricacion}>
                        <Input type="number" step="0.01" value={form.data.porcentaje_fabricacion} onChange={(e) => form.setData('porcentaje_fabricacion', e.target.value)} />
                    </FormField>
                    <FormField label="% Montaje" htmlFor="porcentaje_montaje" error={form.errors.porcentaje_montaje}>
                        <Input type="number" step="0.01" value={form.data.porcentaje_montaje} onChange={(e) => form.setData('porcentaje_montaje', e.target.value)} />
                    </FormField>
                    <FormField label="% Otros" htmlFor="porcentaje_otros" error={form.errors.porcentaje_otros}>
                        <Input type="number" step="0.01" value={form.data.porcentaje_otros} onChange={(e) => form.setData('porcentaje_otros', e.target.value)} />
                    </FormField>
                    <FormField label="Descripción otros" htmlFor="descripcion_otros" error={form.errors.descripcion_otros}>
                        <Input value={form.data.descripcion_otros} onChange={(e) => form.setData('descripcion_otros', e.target.value)} />
                    </FormField>
                    <FormField label="% Avance de obra" htmlFor="porcentaje_obra" error={form.errors.porcentaje_obra}>
                        <Input type="number" step="0.01" value={form.data.porcentaje_obra} onChange={(e) => form.setData('porcentaje_obra', e.target.value)} />
                    </FormField>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                        Guardar contrato
                    </Button>
                </div>
            </form>
        </div>
    );
}

// -- Partidas de la obra --
function PartidasTab({ obra }: { obra: Obra }) {
    const partidas = obra.partidas ?? [];
    const subtotal = partidas.reduce((s, p) => s + Number(p.monto), 0);

    return (
        <div className="rounded-box overflow-hidden border border-base-300">
            <div className="flex items-center justify-between border-b border-base-300 bg-base-200 px-4 py-2">
                <span className="font-semibold">Partidas</span>
                <Link href={`/admin/cob/obras/${obra.id}/partidas/create`} className="btn btn-primary btn-sm gap-1 flex-nowrap">
                    <PlusIcon className="size-4" /> Nueva partida
                </Link>
            </div>

            {partidas.length === 0 ? (
                <p className="text-base-content/50 px-4 py-6 text-center text-sm">Sin partidas. Agrégalas con "Nueva partida".</p>
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

// -- Estimaciones de la obra --
function EstimacionesTab({ obra }: { obra: Obra }) {
    return (
        <div className="rounded-box border border-base-300">
            <div className="flex items-center justify-between border-b border-base-300 px-4 py-2">
                <span className="font-semibold">Estimaciones de la obra</span>
                <a href={`/admin/cob/proyectos/${obra.proyecto_id}/estimaciones/create`} className="btn btn-primary btn-xs">
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
                    {(obra.estimaciones ?? []).map((e) => (
                        <tr key={e.id}>
                            <td>{e.numero_estimacion}</td>
                            <td>{e.folio ?? '-'}</td>
                            <td>{e.inicio && e.fin ? `${fmtFecha(e.inicio)} - ${fmtFecha(e.fin)}` : '-'}</td>
                            <td className="text-right">{formatearMXN(Number(e.monto_estimado))}</td>
                            <td className="text-right">{formatearMXN(Number(e.monto_pagado))}</td>
                            <td><EstadoBadge estado={e.estado} /></td>
                            <td>
                                <a href={`/admin/cob/proyectos/${obra.proyecto_id}/estimaciones/${e.id}/edit`} className="btn btn-ghost btn-xs">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    ))}
                    {(obra.estimaciones ?? []).length === 0 && (
                        <tr><td colSpan={7} className="py-6 text-center opacity-50">No hay estimaciones</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function fmtFecha(fecha: string | null): string {
    if (!fecha) return '-';
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
