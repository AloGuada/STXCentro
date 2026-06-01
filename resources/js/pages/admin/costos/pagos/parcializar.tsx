import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPago } from '@/types/models';
import { PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';

type Props = {
    pago: CostosPago;
    saldoPendiente: number;
    montoPagado: number;
};

export default function PagosParcializar({ pago, saldoPendiente, montoPagado }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/pagos' },
        { title: 'Pagos', href: '/admin/costos/pagos' },
        { title: pago.folio, href: `/admin/costos/pagos/${pago.id}` },
        { title: 'Parcializar', href: `/admin/costos/pagos/${pago.id}/parcializar` },
    ];

    const montoPago = Number(pago.monto_pago);
    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
    const pctPagado = montoPago > 0 ? Math.min(100, (montoPagado / montoPago) * 100) : 0;

    const { data, setData, post, processing, errors } = useForm({
        monto: '',
        fecha_programada: '',
    });

    const montoNum = parseFloat(data.monto) || 0;
    const restante = Math.max(0, saldoPendiente - montoNum);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/pagos/${pago.id}/parcializar`);
    };

    const llenarSaldo = () => {
        setData('monto', String(saldoPendiente));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Parcializar - ${pago.folio}`} />

            <div className="mx-auto max-w-2xl p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Parcializar Pago</h1>
                        <div className="mt-1 flex items-center gap-2">
                            <span className="font-medium">{pago.folio}</span>
                            <span className={`badge ${PAGO_ESTATUS_COLORS[pago.estatus]}`}>
                                {PAGO_ESTATUS_LABELS[pago.estatus]}
                            </span>
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/costos/pagos/${pago.id}`}>Volver</Link>
                    </Button>
                </div>

                {/* Resumen visual */}
                <div className="mb-6 rounded-lg border border-base-300 p-4 space-y-3">
                    <div className="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div className="text-xs text-base-content/60 uppercase">Total</div>
                            <div className="text-lg font-bold">{formatMoney(montoPago)}</div>
                        </div>
                        <div>
                            <div className="text-xs text-base-content/60 uppercase">Distribuido</div>
                            <div className="text-lg font-bold text-success">{formatMoney(montoPagado)}</div>
                        </div>
                        <div>
                            <div className="text-xs text-base-content/60 uppercase">Saldo</div>
                            <div className="text-lg font-bold text-warning">{formatMoney(saldoPendiente)}</div>
                        </div>
                    </div>
                    <div className="w-full bg-base-300 rounded-full h-2.5">
                        <div
                            className="bg-success h-2.5 rounded-full transition-all"
                            style={{ width: `${pctPagado}%` }}
                        />
                    </div>
                    {(pago.pagos_parciales?.length ?? 0) > 0 && (
                        <div className="text-xs text-base-content/60">
                            {pago.pagos_parciales!.length} parcialidad(es) registrada(s)
                        </div>
                    )}
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="form-control">
                        <label className="mb-1 text-sm font-medium">Monto de esta parcialidad</label>
                        <div className="flex gap-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={saldoPendiente}
                                className={`input input-bordered flex-1 ${errors.monto ? 'input-error' : ''}`}
                                value={data.monto}
                                onChange={(e) => setData('monto', e.target.value)}
                                placeholder={`Max: ${formatMoney(saldoPendiente)}`}
                                required
                            />
                            <button type="button" className="btn btn-outline btn-sm self-center" onClick={llenarSaldo}>
                                Pagar todo
                            </button>
                        </div>
                        {errors.monto && <span className="mt-1 text-xs text-error">{errors.monto}</span>}
                    </div>

                    <div className="form-control">
                        <label className="mb-1 text-sm font-medium">Fecha programada de pago</label>
                        <input
                            type="date"
                            className={`input input-bordered ${errors.fecha_programada ? 'input-error' : ''}`}
                            value={data.fecha_programada}
                            onChange={(e) => setData('fecha_programada', e.target.value)}
                            required
                        />
                        {errors.fecha_programada && <span className="mt-1 text-xs text-error">{errors.fecha_programada}</span>}
                    </div>

                    {montoNum > 0 && restante > 0.01 && (
                        <div className="rounded-lg bg-info/10 border border-info/30 p-3 text-sm text-info">
                            Despues de esta parcialidad quedara un saldo pendiente de <strong>{formatMoney(restante)}</strong> que podra parcializarse posteriormente.
                        </div>
                    )}

                    {montoNum > 0 && restante < 0.01 && (
                        <div className="rounded-lg bg-success/10 border border-success/30 p-3 text-sm text-success">
                            Esta parcialidad cubre el saldo completo.
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/costos/pagos/${pago.id}`}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing || montoNum <= 0}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Registrar parcialidad
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
