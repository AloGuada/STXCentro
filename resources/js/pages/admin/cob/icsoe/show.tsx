import IcsoeDesgloseTabla from '@/components/cob/icsoe-desglose-tabla';
import { formatearMXN } from '@/components/cob/money-display';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobIcsoeMetodo, CobIcsoeSbcAnio, CobIcsoeSeguimiento } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircleIcon, CheckCircle2Icon, PrinterIcon, RefreshCwIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    seguimiento: CobIcsoeSeguimiento;
    valorAEjecutarVivo: number;
    metodoOptions: Record<string, string>;
    sbcAnios: Pick<CobIcsoeSbcAnio, 'anio' | 'sbc'>[];
};

type TabKey = 'desglose' | 'configuracion';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'desglose', label: 'Desglose mensual' },
    { key: 'configuracion', label: 'Configuración' },
];

export default function IcsoeShow({ seguimiento, valorAEjecutarVivo, metodoOptions }: Props) {
    const { can } = useCan();
    const [tab, setTab] = useState<TabKey>('desglose');

    const puedeEditar = can('cob.icsoe.editar');
    const pendiente = seguimiento.estatus === 'pendiente_verificacion';
    const proyecto = seguimiento.proyecto;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/dashboard' },
        { title: 'ICSOE / SIROC', href: '/admin/cob/icsoe' },
        { title: proyecto?.no ?? `#${seguimiento.id}`, href: `/admin/cob/icsoe/${seguimiento.id}` },
    ];

    const form = useForm({
        metodo: seguimiento.metodo,
        fecha_inicio: seguimiento.fecha_inicio,
        fecha_fin: seguimiento.fecha_fin,
        superficie_m2: seguimiento.superficie_m2 ?? '',
        costo_m2: seguimiento.costo_m2 ?? '',
        porcentaje_mo: seguimiento.porcentaje_mo,
        prima_riesgo: seguimiento.prima_riesgo,
        notas: seguimiento.notas ?? '',
    });

    const guardarConfiguracion = () => {
        form.put(`/admin/cob/icsoe/${seguimiento.id}`, { preserveScroll: true });
    };

    const verificar = () => router.post(`/admin/cob/icsoe/${seguimiento.id}/verificar`, {}, { preserveScroll: true });
    const recalcular = () => router.post(`/admin/cob/icsoe/${seguimiento.id}/recalcular`, {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`ICSOE ${proyecto?.no ?? ''}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4 print:hidden">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {proyecto?.no} <span className="text-base-content/60">{proyecto?.descripcion}</span>
                        </h1>
                        <p className="text-sm text-base-content/60">
                            Control mensual SIROC · {seguimiento.fecha_inicio} al {seguimiento.fecha_fin} · {seguimiento.total_dias} días
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {puedeEditar && (
                            <Button type="button" variant="outline" onClick={recalcular}>
                                <RefreshCwIcon className="size-4" />
                                Recalcular
                            </Button>
                        )}
                        <Button type="button" variant="outline" onClick={() => window.print()}>
                            <PrinterIcon className="size-4" />
                            Imprimir
                        </Button>
                    </div>
                </div>

                {pendiente && (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-box border border-warning bg-warning/10 p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircleIcon className="mt-0.5 size-5 text-warning" />
                            <div>
                                <p className="font-medium">Pendiente de verificación</p>
                                <p className="text-sm text-base-content/70">
                                    {seguimiento.motivo_cambio ?? 'El valor a ejecutar del proyecto cambió.'} El seguimiento ya se recalculó:
                                    la base pasó de{' '}
                                    <span className="font-medium">{formatearMXN(Number(seguimiento.monto_base_anterior))}</span> a{' '}
                                    <span className="font-medium">{formatearMXN(Number(seguimiento.monto_base))}</span>.
                                </p>
                            </div>
                        </div>
                        {can('cob.icsoe.verificar') && (
                            <Button type="button" onClick={verificar}>
                                <CheckCircle2Icon className="size-4" />
                                Confirmar
                            </Button>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4 rounded-box border border-base-300 p-4 md:grid-cols-4">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Método</p>
                        <p className="text-lg font-semibold">{metodoOptions[seguimiento.metodo]}</p>
                    </div>
                    {seguimiento.metodo === 'superficie' ? (
                        <>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Superficie</p>
                                <p className="text-lg font-semibold">{Number(seguimiento.superficie_m2 ?? 0).toLocaleString('es-MX')} m²</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Costo DOF por m²</p>
                                <p className="text-lg font-semibold">{formatearMXN(Number(seguimiento.costo_m2 ?? 0))}</p>
                            </div>
                        </>
                    ) : (
                        <>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Valor a ejecutar</p>
                                <p className="text-lg font-semibold">{formatearMXN(Number(seguimiento.monto_base))}</p>
                                {valorAEjecutarVivo !== Number(seguimiento.monto_base) && (
                                    <p className="text-xs text-warning">Hoy vale {formatearMXN(valorAEjecutarVivo)}</p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">% de M.O.</p>
                                <p className="text-lg font-semibold">{Number(seguimiento.porcentaje_mo)}%</p>
                            </div>
                        </>
                    )}
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Prima de riesgo</p>
                        <p className="text-lg font-semibold">{Number(seguimiento.prima_riesgo)}%</p>
                    </div>
                </div>

                <div className="tabs tabs-bordered print:hidden">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            className={`tab ${tab === t.key ? 'tab-active' : ''}`}
                            onClick={() => setTab(t.key)}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'desglose' && (
                    <IcsoeDesgloseTabla seguimiento={seguimiento} meses={seguimiento.meses ?? []} puedeEditar={puedeEditar} />
                )}

                {tab === 'configuracion' && (
                    <div className="max-w-3xl space-y-4 rounded-box border border-base-300 p-6">
                        <div>
                            <label className="mb-1 block text-sm font-medium">Método de estimación IMSS</label>
                            <Select
                                value={form.data.metodo}
                                onValueChange={(valor) => form.setData('metodo', valor as CobIcsoeMetodo)}
                                disabled={!puedeEditar}
                            >
                                {Object.entries(metodoOptions).map(([valor, etiqueta]) => (
                                    <SelectItem key={valor} value={valor}>
                                        {etiqueta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium">Fecha de inicio</label>
                                <Input
                                    type="date"
                                    value={form.data.fecha_inicio}
                                    onChange={(e) => form.setData('fecha_inicio', e.target.value)}
                                    disabled={!puedeEditar}
                                />
                                {form.errors.fecha_inicio && <p className="mt-1 text-sm text-error">{form.errors.fecha_inicio}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">Fecha de término</label>
                                <Input
                                    type="date"
                                    value={form.data.fecha_fin}
                                    onChange={(e) => form.setData('fecha_fin', e.target.value)}
                                    disabled={!puedeEditar}
                                />
                                {form.errors.fecha_fin && <p className="mt-1 text-sm text-error">{form.errors.fecha_fin}</p>}
                            </div>

                            {form.data.metodo === 'superficie' ? (
                                <>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Superficie de construcción (m²)</label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={form.data.superficie_m2}
                                            onChange={(e) => form.setData('superficie_m2', e.target.value)}
                                            disabled={!puedeEditar}
                                        />
                                        {form.errors.superficie_m2 && <p className="mt-1 text-sm text-error">{form.errors.superficie_m2}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Costo IMSS del DOF ($/m²)</label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={form.data.costo_m2}
                                            onChange={(e) => form.setData('costo_m2', e.target.value)}
                                            disabled={!puedeEditar}
                                        />
                                        {form.errors.costo_m2 && <p className="mt-1 text-sm text-error">{form.errors.costo_m2}</p>}
                                    </div>
                                </>
                            ) : (
                                <div>
                                    <label className="mb-1 block text-sm font-medium">% de M.O. a acumular</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="100"
                                        value={form.data.porcentaje_mo}
                                        onChange={(e) => form.setData('porcentaje_mo', e.target.value)}
                                        disabled={!puedeEditar}
                                    />
                                    <p className="mt-1 text-xs text-base-content/60">
                                        Se aplica al valor a ejecutar del proyecto, que sale de cobranza (comparativo en precios unitarios,
                                        partidas en precio alzado).
                                    </p>
                                    {form.errors.porcentaje_mo && <p className="mt-1 text-sm text-error">{form.errors.porcentaje_mo}</p>}
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-sm font-medium">Prima de riesgo IMSS de la empresa (%)</label>
                                <Input
                                    type="number"
                                    step="0.00001"
                                    value={form.data.prima_riesgo}
                                    onChange={(e) => form.setData('prima_riesgo', e.target.value)}
                                    disabled={!puedeEditar}
                                />
                                {form.errors.prima_riesgo && <p className="mt-1 text-sm text-error">{form.errors.prima_riesgo}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium">Notas</label>
                            <Input value={form.data.notas} onChange={(e) => form.setData('notas', e.target.value)} disabled={!puedeEditar} />
                        </div>

                        {puedeEditar && (
                            <div className="flex justify-end">
                                <Button type="button" onClick={guardarConfiguracion} disabled={form.processing}>
                                    Guardar y recalcular
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
