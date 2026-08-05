import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosComplementoPago } from '@/types/models';
import { COMPLEMENTO_PAGO_ESTATUS_COLORS, COMPLEMENTO_PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

/** Qué pasó con cada factura que referencia el complemento. */
type ResultadoComplemento = {
    uuid: string;
    aplicado: boolean;
    factura: string | null;
    detalle: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/portal' },
    { title: 'Complementos de pago', href: '/portal/complementos' },
];

type Props = {
    complementos: CostosComplementoPago[];
    resultado?: ResultadoComplemento[] | null;
};

const money = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
const fecha = (d: string) => new Date(d).toLocaleDateString('es-MX');

export default function PortalComplementosIndex({ complementos, resultado }: Props) {
    const pendientes = complementos.filter((c) => c.estatus !== 'cumplido');


    const form = useForm<{ xml: File | null; pdf: File | null }>({ xml: null, pdf: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/complementos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('xml', 'pdf'),
        });
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Complementos de pago" />

            <div className="p-6 space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Complementos de pago</h1>
                    <p className="text-sm text-base-content/60 mt-1">
                        Por cada pago a una factura PPD debe emitir y subir el complemento de pago (CFDI tipo P).
                        Mientras existan complementos pendientes no se generarán nuevas órdenes ni solicitudes de pago a su favor.
                    </p>
                </div>

                {pendientes.length > 0 && (
                    <div className="alert alert-warning">
                        <span>
                            Tiene {pendientes.length} complemento(s) de pago pendiente(s). Súbalos para regularizar su cuenta.
                        </span>
                    </div>
                )}

                <form onSubmit={submit} className="card bg-base-200 p-4 space-y-3">
                    <h2 className="font-semibold">Subir complemento de pago</h2>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <label className="form-control">
                            <span className="label-text">XML (CFDI tipo P) *</span>
                            <input
                                type="file"
                                accept=".xml,text/xml"
                                className="file-input file-input-bordered file-input-sm"
                                onChange={(e) => form.setData('xml', e.target.files?.[0] ?? null)}
                            />
                        </label>
                        <label className="form-control">
                            <span className="label-text">PDF (opcional)</span>
                            <input
                                type="file"
                                accept="application/pdf"
                                className="file-input file-input-bordered file-input-sm"
                                onChange={(e) => form.setData('pdf', e.target.files?.[0] ?? null)}
                            />
                        </label>
                        <button type="submit" className="btn btn-primary btn-sm" disabled={!form.data.xml || form.processing}>
                            Subir
                        </button>
                    </div>
                    {form.errors.xml && <p className="text-error text-sm">{form.errors.xml}</p>}

                    {resultado && resultado.length > 0 && (
                        <div className="rounded-box border border-base-300 bg-base-100 p-3">
                            <p className="mb-2 text-sm font-medium">Resultado del último complemento</p>
                            <ul className="space-y-1 text-sm">
                                {resultado.map((r) => (
                                    <li key={r.uuid} className="flex gap-2">
                                        <span className={r.aplicado ? 'text-success' : 'text-error'}>
                                            {r.aplicado ? '✓' : '✗'}
                                        </span>
                                        <span>
                                            <span className="font-medium">{r.factura ?? r.uuid}</span>{' '}
                                            <span className="text-base-content/60">{r.detalle}</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </form>

                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Factura</th>
                                <th>Monto del pago</th>
                                <th>Complementado</th>
                                <th>Fecha de pago</th>
                                <th>Fecha límite</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            {complementos.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-center text-base-content/60">
                                        No tiene obligaciones de complemento de pago.
                                    </td>
                                </tr>
                            )}
                            {complementos.map((c) => (
                                <tr key={c.id}>
                                    <td>{c.folio}</td>
                                    <td>{c.factura?.folio ?? '—'}</td>
                                    <td>{money(c.monto_pago)}</td>
                                    <td>
                                        {money(c.monto_cubierto)}
                                        {Number(c.monto_cubierto) > 0 &&
                                            Number(c.monto_cubierto) < Number(c.monto_pago) && (
                                                <span className="ml-1 text-xs text-warning">
                                                    (faltan {money(Number(c.monto_pago) - Number(c.monto_cubierto))})
                                                </span>
                                            )}
                                    </td>
                                    <td>{fecha(c.fecha_pago)}</td>
                                    <td>{fecha(c.fecha_limite)}</td>
                                    <td>
                                        <span className={`badge ${COMPLEMENTO_PAGO_ESTATUS_COLORS[c.estatus]}`}>
                                            {COMPLEMENTO_PAGO_ESTATUS_LABELS[c.estatus]}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </PortalLayout>
    );
}
