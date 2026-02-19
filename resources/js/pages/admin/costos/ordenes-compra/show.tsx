import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, CostosOrdenCompraEstatus } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { FileIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    ordenCompra: CostosOrdenCompra;
};

const steps: { key: CostosOrdenCompraEstatus; label: string }[] = [
    { key: 'pendiente_factura', label: 'Pend. Factura' },
    { key: 'pendiente_entrega', label: 'Pend. Entrega' },
    { key: 'pendiente_aprobacion', label: 'Pend. Aprobación' },
    { key: 'pendiente_pago', label: 'Pend. Pago' },
    { key: 'pagada', label: 'Pagada' },
];

function getStepIndex(estatus: CostosOrdenCompraEstatus): number {
    if (estatus === 'cancelada') return -1;
    return steps.findIndex((s) => s.key === estatus);
}

export default function OrdenesCompraShow({ ordenCompra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/ordenes-compra' },
        { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
        { title: ordenCompra.folio, href: `/admin/costos/ordenes-compra/${ordenCompra.id}` },
    ];

    const { can } = useCan();
    const currentStep = getStepIndex(ordenCompra.estatus);
    const [activeTab, setActiveTab] = useState<'datos' | 'facturas'>('datos');

    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    const handleCancelar = () => {
        if (confirm('Cancelar esta orden de compra?')) {
            router.post(`/admin/costos/ordenes-compra/${ordenCompra.id}/cancelar`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ordenCompra.folio} />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{ordenCompra.folio}</h1>
                        <div className="flex items-center gap-3 mt-1">
                            <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[ordenCompra.estatus]}`}>
                                {ORDEN_COMPRA_ESTATUS_LABELS[ordenCompra.estatus]}
                            </span>
                            <span className="text-lg font-semibold">{formatMoney(ordenCompra.total)}</span>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {['pendiente_factura', 'pendiente_entrega', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && can('costos.ordenes-compra.cancelar') && (
                            <Button variant="destructive" onClick={handleCancelar}>Cancelar</Button>
                        )}
                    </div>
                </div>

                {/* Stepper */}
                {ordenCompra.estatus !== 'cancelada' && (
                    <ul className="steps steps-horizontal w-full mb-6">
                        {steps.map((step, idx) => (
                            <li key={step.key} className={`step ${idx <= currentStep ? 'step-primary' : ''}`}>
                                {step.label}
                            </li>
                        ))}
                    </ul>
                )}

                {/* Tabs */}
                <div className="tabs tabs-bordered mb-6">
                    <button className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>Datos</button>
                    <button className={`tab ${activeTab === 'facturas' ? 'tab-active' : ''}`} onClick={() => setActiveTab('facturas')}>Facturas ({ordenCompra.facturas?.length ?? 0})</button>
                </div>

                {activeTab === 'datos' && (
                    <div className="space-y-6">
                        {/* Info general */}
                        <div className="grid grid-cols-2 gap-6">
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Proveedor</span>
                                    <p className="font-medium">{ordenCompra.proveedor?.razon_social}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Departamento</span>
                                    <p className="font-medium">{ordenCompra.departamento?.descripcion}</p>
                                </div>
                                {ordenCompra.referencia && (
                                    <div>
                                        <span className="text-sm text-base-content/60">Folio Referencia</span>
                                        <p className="font-medium">{ordenCompra.referencia}</p>
                                    </div>
                                )}
                            </div>
                            <div className="space-y-3">
                                <div>
                                    <span className="text-sm text-base-content/60">Moneda</span>
                                    <p className="font-medium">{TIPO_MONEDA_LABELS[ordenCompra.moneda] ?? ordenCompra.moneda}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-base-content/60">Fecha Entrega Esperada</span>
                                    <p className="font-medium">{ordenCompra.fecha_entrega_esperada ? new Date(ordenCompra.fecha_entrega_esperada).toLocaleDateString() : '-'}</p>
                                </div>
                                {ordenCompra.archivo_path && (
                                    <div>
                                        <span className="text-sm text-base-content/60">Archivo</span>
                                        <p>
                                            <a href={`/storage/${ordenCompra.archivo_path}`} target="_blank" rel="noopener noreferrer" className="link link-primary inline-flex items-center gap-1">
                                                <FileIcon className="size-4" /> Ver archivo
                                            </a>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {ordenCompra.notas && (
                            <div>
                                <span className="text-sm text-base-content/60">Notas</span>
                                <p>{ordenCompra.notas}</p>
                            </div>
                        )}

                        {/* Rubros table */}
                        <div>
                            <h2 className="text-lg font-medium mb-3">Rubros</h2>
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Obra</th>
                                            <th>Rubro</th>
                                            <th className="text-right">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ordenCompra.detalles?.map((d) => (
                                            <tr key={d.id}>
                                                <td>{d.obra_rubro?.obra ? `${d.obra_rubro.obra.no} - ${d.obra_rubro.obra.descripcion}` : '-'}</td>
                                                <td>{d.obra_rubro?.rubro?.codigo} - {d.obra_rubro?.rubro?.descripcion}</td>
                                                <td className="text-right">{formatMoney(d.monto)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'facturas' && (
                    <div>
                        {(!ordenCompra.facturas || ordenCompra.facturas.length === 0) ? (
                            <p className="text-base-content/60">No hay facturas registradas.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th className="text-right">Total</th>
                                            <th>Estatus</th>
                                            <th>Entregas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ordenCompra.facturas.map((f) => (
                                            <tr key={f.id}>
                                                <td>
                                                    <Link href={`/admin/costos/facturas/${f.id}`} className="link link-primary">
                                                        {f.folio}
                                                    </Link>
                                                </td>
                                                <td>{f.fecha_factura ? new Date(f.fecha_factura).toLocaleDateString() : '-'}</td>
                                                <td className="text-right">{formatMoney(f.total)}</td>
                                                <td>
                                                    <span className={`badge ${FACTURA_ESTATUS_COLORS[f.estatus]}`}>
                                                        {FACTURA_ESTATUS_LABELS[f.estatus]}
                                                    </span>
                                                </td>
                                                <td>{f.entregas?.length ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
