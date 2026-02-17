import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, ProdCorte, ProdGrupoTrabajo, ProdLiquidacion, ProdLiquidacionDetalle, ProdLiquidacionEmpleado, ProdPagoExtra, ProdRegistro, ProdTipoPagoExtra, Usuario } from '@/types/models';
import { Head, router } from '@inertiajs/react';

type LiquidacionFull = ProdLiquidacion & {
    grupo_trabajo: ProdGrupoTrabajo;
    generador: Usuario;
    detalles: ProdLiquidacionDetalle[];
    empleados: ProdLiquidacionEmpleado[];
};

type CorteFull = ProdCorte & {
    liquidaciones: LiquidacionFull[];
};

type RegistroPreview = ProdRegistro & {
    concepto: Concepto & { obra: Obra };
    grupo_trabajo: ProdGrupoTrabajo;
};

type PagoExtraPreview = ProdPagoExtra & {
    tipo: ProdTipoPagoExtra;
    grupo_trabajo: ProdGrupoTrabajo;
};

type Props = {
    corte: CorteFull;
    registrosPreview?: Record<string, RegistroPreview[]>;
    pagosExtraPreview?: Record<string, PagoExtraPreview[]>;
};

export default function CortesShow({ corte, registrosPreview, pagosExtraPreview }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Cortes', href: '/admin/prod/cortes' },
        { title: `Semana ${corte.semana}`, href: `/admin/prod/cortes/${corte.id}` },
    ];

    const handleCerrar = () => {
        if (confirm('Estas seguro de cerrar este corte? Se generaran las liquidaciones automaticamente.')) {
            router.post(`/admin/prod/cortes/${corte.id}/cerrar`, {}, { preserveScroll: true });
        }
    };

    const handleDelete = () => {
        if (confirm('Estas seguro de eliminar este corte?')) {
            router.delete(`/admin/prod/cortes/${corte.id}`);
        }
    };

    const fmt = (n: number, decimals = 2) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    // Combine all grupo_trabajo_ids from both previews
    const allGrupoIds = new Set([
        ...Object.keys(registrosPreview ?? {}),
        ...Object.keys(pagosExtraPreview ?? {}),
    ]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Corte Semana ${corte.semana}`} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Corte Semana {corte.semana}</h1>
                        <p className="text-sm text-gray-500">{corte.fecha_inicio} — {corte.fecha_fin}</p>
                        <span className={`mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${corte.cerrado ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'}`}>
                            {corte.cerrado ? 'Cerrado' : 'Abierto'}
                        </span>
                    </div>
                    <div className="flex gap-2">
                        {!corte.cerrado && (
                            <>
                                <Button variant="destructive" onClick={handleDelete}>Eliminar</Button>
                                <Button onClick={handleCerrar}>Cerrar Corte</Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Preview for open corte */}
                {!corte.cerrado && allGrupoIds.size > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-3 text-lg font-semibold">Vista Previa</h2>
                        {Array.from(allGrupoIds).map((grupoId) => {
                            const regs = registrosPreview?.[grupoId] ?? [];
                            const extras = pagosExtraPreview?.[grupoId] ?? [];
                            const grupoName = regs[0]?.grupo_trabajo?.descripcion ?? extras[0]?.grupo_trabajo?.descripcion ?? `Grupo ${grupoId}`;

                            return (
                                <div key={grupoId} className="mb-4 rounded border p-4">
                                    <h3 className="mb-2 text-base font-semibold">{grupoName}</h3>

                                    {regs.length > 0 && (
                                        <>
                                            <div className="mb-1 flex items-center gap-2">
                                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Registros</span>
                                                <span className="badge badge-sm badge-info">{regs.length}</span>
                                            </div>
                                            <table className="mb-3 w-full text-sm">
                                                <thead>
                                                    <tr className="border-b text-left">
                                                        <th className="py-1">Concepto</th>
                                                        <th className="py-1 text-right">Cantidad</th>
                                                        <th className="py-1">Fecha</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {regs.map((r) => (
                                                        <tr key={r.id} className="border-b">
                                                            <td className="py-1">{r.concepto?.marca} - {r.concepto?.descripcion}</td>
                                                            <td className="py-1 text-right font-mono">{r.cantidad}</td>
                                                            <td className="py-1 font-mono text-xs">{r.fecha}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </>
                                    )}

                                    {extras.length > 0 && (
                                        <>
                                            <div className="mb-1 flex items-center gap-2">
                                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Pagos Extra</span>
                                                <span className="badge badge-sm badge-warning">{extras.length}</span>
                                            </div>
                                            <table className="mb-2 w-full text-sm">
                                                <thead>
                                                    <tr className="border-b text-left">
                                                        <th className="py-1">Tipo</th>
                                                        <th className="py-1">Descripcion</th>
                                                        <th className="py-1 text-right">Precio</th>
                                                        <th className="py-1 text-right">Dias</th>
                                                        <th className="py-1 text-right">Personas</th>
                                                        <th className="py-1 text-right">Monto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {extras.map((pe) => (
                                                        <tr key={pe.id} className="border-b">
                                                            <td className="py-1">{pe.tipo?.descripcion}</td>
                                                            <td className="py-1">{pe.descripcion}</td>
                                                            <td className="py-1 text-right font-mono">${fmt(pe.precio)}</td>
                                                            <td className="py-1 text-right font-mono">{pe.dias}</td>
                                                            <td className="py-1 text-right font-mono">{pe.personas}</td>
                                                            <td className="py-1 text-right font-mono">${fmt(Number(pe.monto))}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {!corte.cerrado && allGrupoIds.size === 0 && (
                    <div className="rounded border border-dashed p-8 text-center text-gray-500">
                        Al cerrar el corte se generaran las liquidaciones automaticamente con los registros del periodo.
                    </div>
                )}

                {/* Closed corte: liquidaciones */}
                {corte.cerrado && corte.liquidaciones.length === 0 && (
                    <div className="rounded border border-dashed p-8 text-center text-gray-500">
                        No se generaron liquidaciones para este corte (sin registros en el periodo).
                    </div>
                )}

                {corte.liquidaciones.map((liq) => {
                    const liqPagosExtra = pagosExtraPreview?.[String(liq.grupo_trabajo_id)] ?? [];

                    return (
                        <div key={liq.id} className="mb-6 rounded border p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-lg font-semibold">{liq.grupo_trabajo?.descripcion}</h2>
                                <div className="text-right text-sm text-gray-500">
                                    Generado: {liq.generado_en} por {liq.generador?.name}
                                </div>
                            </div>

                            {/* Detalle */}
                            <h3 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Detalle de Produccion</h3>
                            <table className="mb-3 w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="py-1">Concepto</th>
                                        <th className="py-1 text-right">Cantidad</th>
                                        <th className="py-1 text-right">Kilos</th>
                                        <th className="py-1 text-right">$/kg</th>
                                        <th className="py-1 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {liq.detalles?.map((d) => (
                                        <tr key={d.id} className="border-b">
                                            <td className="py-1">ID: {d.concepto_id}</td>
                                            <td className="py-1 text-right font-mono">{d.cantidad}</td>
                                            <td className="py-1 text-right font-mono">{fmt(d.kilos, 3)}</td>
                                            <td className="py-1 text-right font-mono">{fmt(d.precio_kilo_aplicado, 4)}</td>
                                            <td className="py-1 text-right font-mono">${fmt(d.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Pagos Extra */}
                            <h3 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Pagos Extra</h3>
                            {liqPagosExtra.length > 0 ? (
                                <div className="mb-2 space-y-1">
                                    {liqPagosExtra.map((pe) => (
                                        <div key={pe.id} className="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                            <span className="text-sm">{pe.tipo?.descripcion}: {pe.descripcion}</span>
                                            <span className="font-mono text-sm">
                                                ${fmt(pe.precio)} x {pe.dias}d x {pe.personas}p = ${fmt(Number(pe.monto))}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="mb-2 text-xs text-gray-400">Sin pagos extra</p>
                            )}

                            {/* Totales */}
                            <div className="mt-3 border-t pt-2">
                                <div className="flex justify-between text-sm">
                                    <span>Kilos:</span><span className="font-mono">{fmt(liq.total_kilos, 3)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span>Produccion:</span><span className="font-mono">${fmt(liq.total_produccion)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span>Extras:</span><span className="font-mono">${fmt(liq.total_extras)}</span>
                                </div>
                                <div className="flex justify-between text-sm font-bold">
                                    <span>Total Final:</span><span className="font-mono">${fmt(liq.total_final)}</span>
                                </div>
                            </div>

                            {/* Empleados */}
                            {liq.empleados && liq.empleados.length > 0 && (
                                <>
                                    <h3 className="mb-1 mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Distribucion a Empleados</h3>
                                    <div className="space-y-1">
                                        {liq.empleados.map((emp) => (
                                            <div key={emp.id} className="flex items-center justify-between text-sm">
                                                <span>{emp.nombre} ({emp.no_empleado || '-'})</span>
                                                <span className="font-mono">{Number(emp.porcentaje).toFixed(2)}% = ${fmt(emp.monto_asignado)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    );
                })}
            </div>
        </AppLayout>
    );
}
