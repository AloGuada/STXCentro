import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCorte, ProdExtra, ProdLiquidacion, ProdLiquidacionDetalle, ProdLiquidacionEmpleado, ProdGrupoTrabajo, Usuario } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2Icon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type LiquidacionFull = ProdLiquidacion & {
    grupo_trabajo: ProdGrupoTrabajo;
    generador: Usuario;
    detalles: ProdLiquidacionDetalle[];
    extras: ProdExtra[];
    empleados: ProdLiquidacionEmpleado[];
};

type CorteFull = ProdCorte & {
    liquidaciones: LiquidacionFull[];
};

type Props = {
    corte: CorteFull;
};

function ExtraForm({ corteId, liquidacionId }: { corteId: number; liquidacionId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        descripcion: '',
        monto: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/prod/cortes/${corteId}/liquidaciones/${liquidacionId}/extras`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={handleSubmit} className="mt-2 flex items-end gap-2">
            <div className="flex-1">
                <Input
                    value={data.descripcion}
                    onChange={(e) => setData('descripcion', e.target.value)}
                    placeholder="Descripcion del extra"
                />
                {errors.descripcion && <p className="mt-1 text-xs text-red-500">{errors.descripcion}</p>}
            </div>
            <div className="w-32">
                <Input
                    type="number"
                    step="0.01"
                    min="0"
                    value={data.monto}
                    onChange={(e) => setData('monto', e.target.value)}
                    placeholder="Monto"
                />
                {errors.monto && <p className="mt-1 text-xs text-red-500">{errors.monto}</p>}
            </div>
            <Button type="submit" size="sm" disabled={processing}>
                {processing && <Loader2Icon className="size-3 animate-spin" />}
                Agregar
            </Button>
        </form>
    );
}

export default function CortesShow({ corte }: Props) {
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

    const handleDeleteExtra = (liquidacionId: number, extraId: number) => {
        router.delete(`/admin/prod/cortes/${corte.id}/liquidaciones/${liquidacionId}/extras/${extraId}`, { preserveScroll: true });
    };

    const fmt = (n: number, decimals = 2) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

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

                {corte.liquidaciones.length === 0 && (
                    <div className="rounded border border-dashed p-8 text-center text-gray-500">
                        {corte.cerrado
                            ? 'No se generaron liquidaciones para este corte (sin registros en el periodo).'
                            : 'Al cerrar el corte se generaran las liquidaciones automaticamente con los registros del periodo.'}
                    </div>
                )}

                {corte.liquidaciones.map((liq) => (
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

                        {/* Extras */}
                        <h3 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Extras</h3>
                        {liq.extras && liq.extras.length > 0 ? (
                            <div className="mb-2 space-y-1">
                                {liq.extras.map((ex) => (
                                    <div key={ex.id} className="flex items-center justify-between rounded bg-gray-50 px-2 py-1 dark:bg-gray-800">
                                        <span className="text-sm">{ex.descripcion}</span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm">${fmt(ex.monto)}</span>
                                            <Button type="button" variant="ghost" size="icon" className="h-6 w-6" onClick={() => handleDeleteExtra(liq.id, ex.id)}>
                                                <TrashIcon className="size-3 text-red-500" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mb-2 text-xs text-gray-400">Sin extras</p>
                        )}
                        <ExtraForm corteId={corte.id} liquidacionId={liq.id} />

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
                ))}
            </div>
        </AppLayout>
    );
}
