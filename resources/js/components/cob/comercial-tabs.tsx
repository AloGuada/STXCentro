import { router } from '@inertiajs/react';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EstimacionesGantt } from '@/components/cob/estimaciones-gantt';
import { formatearMXN } from '@/components/cob/money-display';
import { Button } from '@/components/ui/button';
import {
    COB_ADENDA_ESTADO_LABELS,
    COB_ADENDA_TIPO_LABELS,
    COB_ANTICIPO_ESTADO_LABELS,
    COB_COMPARATIVO_ESTADO_LABELS,
    COB_DISPUTA_ESTADO_LABELS,
    type Obra,
} from '@/types/models';

/**
 * Tabs comerciales del hub de cobranza. Operan sobre la obra base del proyecto
 * (donde viven anticipos, adendas, comparativos, etc.); por eso los links siguen
 * usando las rutas `obras/{obra}/...` existentes.
 */

function formatFecha(fecha: string | null): string {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function AnticiposTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function AdendasTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function ComparativosTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function DeduccionesTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function DisputasTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function PenalizacionesTab({ obra }: { obra: Obra }) {
    return (
        <div>
            <div className="mb-4 flex justify-between">
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

export function GanttTab({ obras }: { obras: Obra[] }) {
    const [selectedYear, setSelectedYear] = useState<number | undefined>(undefined);

    const availableYears = useMemo(() => {
        const years = new Set<number>();
        for (const obra of obras) {
            for (const est of obra.estimaciones ?? []) {
                for (const h of est.historial ?? []) {
                    years.add(new Date(h.fecha_cambio).getFullYear());
                }
            }
        }
        return [...years].sort((a, b) => b - a);
    }, [obras]);

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
            <EstimacionesGantt obras={obras} year={selectedYear} />
        </div>
    );
}
