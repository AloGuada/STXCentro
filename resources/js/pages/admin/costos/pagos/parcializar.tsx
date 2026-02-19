import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS, PAGO_TIPO_PAGO_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';

type Props = {
    pago: CostosPago;
};

export default function PagosParcializar({ pago }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/pagos' },
        { title: 'Pagos', href: '/admin/costos/pagos' },
        { title: pago.folio, href: `/admin/costos/pagos/${pago.id}` },
        { title: 'Parcializar', href: `/admin/costos/pagos/${pago.id}/parcializar` },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        parcialidades: { monto: string; fecha_programada: string }[];
    }>({
        parcialidades: [
            { monto: '', fecha_programada: '' },
            { monto: '', fecha_programada: '' },
        ],
    });

    const addRow = () => {
        setData('parcialidades', [...data.parcialidades, { monto: '', fecha_programada: '' }]);
    };

    const removeRow = (index: number) => {
        if (data.parcialidades.length <= 2) return;
        setData('parcialidades', data.parcialidades.filter((_, i) => i !== index));
    };

    const updateRow = (index: number, field: 'monto' | 'fecha_programada', value: string) => {
        const updated = [...data.parcialidades];
        updated[index] = { ...updated[index], [field]: value };
        setData('parcialidades', updated);
    };

    const suma = data.parcialidades.reduce((acc, p) => acc + (parseFloat(p.monto) || 0), 0);
    const montoPago = Number(pago.monto_pago);
    const sumaValida = Math.abs(suma - montoPago) < 0.01;
    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/pagos/${pago.id}/parcializar`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Parcializar - ${pago.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Parcializar Pago</h1>
                        <div className="mt-1 flex items-center gap-2">
                            <span className="font-medium">{pago.folio}</span>
                            <span className={`badge ${PAGO_ESTATUS_COLORS[pago.estatus]}`}>
                                {PAGO_ESTATUS_LABELS[pago.estatus]}
                            </span>
                            <span className="text-sm text-base-content/60">
                                {PAGO_TIPO_PAGO_LABELS[pago.tipo_pago]}
                            </span>
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/costos/pagos/${pago.id}`}>Volver</Link>
                    </Button>
                </div>

                {/* Resumen del pago */}
                <div className="mb-6 grid grid-cols-2 gap-4 rounded-lg border border-base-300 p-4">
                    <div>
                        <span className="text-sm text-base-content/60">Monto Total</span>
                        <p className="text-xl font-bold">{formatMoney(montoPago)}</p>
                    </div>
                    <div>
                        <span className="text-sm text-base-content/60">Fecha Programada</span>
                        <p className="font-medium">
                            {pago.fecha_pago_programada ? new Date(pago.fecha_pago_programada).toLocaleDateString() : '-'}
                        </p>
                    </div>
                </div>

                {/* Formulario de parcialidades */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Monto</th>
                                    <th>Fecha Programada</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.parcialidades.map((p, i) => (
                                    <tr key={i}>
                                        <td>{i + 1}</td>
                                        <td>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="input input-bordered input-sm w-40"
                                                value={p.monto}
                                                onChange={(e) => updateRow(i, 'monto', e.target.value)}
                                                required
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="date"
                                                className="input input-bordered input-sm"
                                                value={p.fecha_programada}
                                                onChange={(e) => updateRow(i, 'fecha_programada', e.target.value)}
                                                required
                                            />
                                        </td>
                                        <td>
                                            {data.parcialidades.length > 2 && (
                                                <button type="button" className="btn btn-ghost btn-xs" onClick={() => removeRow(i)}>
                                                    <TrashIcon className="size-4" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td></td>
                                    <td className={`font-bold ${sumaValida ? 'text-success' : 'text-error'}`}>
                                        Suma: ${suma.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                        {' / '}
                                        ${montoPago.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                    </td>
                                    <td colSpan={2}></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {errors.parcialidades && <p className="text-sm text-error">{errors.parcialidades}</p>}

                    <div className="flex gap-2">
                        <button type="button" className="btn btn-outline btn-sm" onClick={addRow}>
                            <PlusIcon className="size-4" /> Agregar Parcialidad
                        </button>
                        <Button type="submit" disabled={processing || !sumaValida}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Parcializar
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
