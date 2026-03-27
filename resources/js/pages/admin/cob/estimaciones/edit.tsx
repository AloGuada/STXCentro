import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    COB_ESTIMACION_ESTADO_LABELS,
    type CobEstimacion,
    type CobEstimacionEstado,
    type CobTipoRetencion,
    type Obra,
} from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

type Props = {
    obra: Obra;
    estimacion: CobEstimacion;
    tiposRetencion: CobTipoRetencion[];
};

export default function EstimacionEdit({ obra, estimacion, tiposRetencion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: `Estimacion #${estimacion.numero_estimacion}`, href: '#' },
    ];

    const toDateInput = (value: string | null | undefined): string => {
        if (!value) return '';
        return value.substring(0, 10);
    };

    const form = useForm({
        folio: estimacion.folio ?? '',
        tipo: estimacion.tipo ?? '',
        fecha_emision: toDateInput(estimacion.fecha_emision),
        inicio: toDateInput(estimacion.inicio),
        fin: toDateInput(estimacion.fin),
        monto_estimado: String(estimacion.monto_estimado),
        monto_total: String(estimacion.monto_total),
        moneda: estimacion.moneda,
        comentarios: estimacion.comentarios ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/obras/${obra.id}/estimaciones/${estimacion.id}`);
    };

    // State change form
    const [showEstadoForm, setShowEstadoForm] = useState(false);
    const estadoForm = useForm({ estado: '', fecha_cambio: '', folio: '', comentario: '' });

    const handleCambiarEstado = (e: FormEvent) => {
        e.preventDefault();
        estadoForm.post(`/admin/cob/obras/${obra.id}/estimaciones/${estimacion.id}/cambiar-estado`, {
            onSuccess: () => { setShowEstadoForm(false); estadoForm.reset(); },
        });
    };

    // Transitions map
    const transiciones: Record<string, string[]> = {
        pendiente: ['generada'],
        generada: ['ingresada'],
        ingresada: ['revisada'],
        revisada: ['autorizada'],
        autorizada: ['facturada'],
        facturada: ['pago_parcial', 'pagado'],
        pago_parcial: ['pagado'],
        pagado: [],
    };

    const estadosSiguientes = transiciones[estimacion.estado] ?? [];

    // Payment form
    const [showPagoForm, setShowPagoForm] = useState(false);
    const pagoForm = useForm({ monto_pagado: '', fecha_pago: '', folio: '', comprobante: null as File | null });

    const handleRegistrarPago = (e: FormEvent) => {
        e.preventDefault();
        pagoForm.post(`/admin/cob/obras/${obra.id}/estimaciones/${estimacion.id}/pagos`, {
            forceFormData: true,
            onSuccess: () => { setShowPagoForm(false); pagoForm.reset(); },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Estimacion #${estimacion.numero_estimacion}`} />

            <div className="p-6 space-y-6">
                {/* Header with estado */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Estimacion #{estimacion.numero_estimacion}</h1>
                        <p className="text-sm opacity-70">Obra {obra.no} - {obra.descripcion}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <EstadoBadge estado={estimacion.estado} />
                        {estadosSiguientes.length > 0 && (
                            <Button size="sm" variant="outline" onClick={() => setShowEstadoForm(!showEstadoForm)}>
                                Cambiar Estado
                            </Button>
                        )}
                    </div>
                </div>

                {/* Estado change form */}
                {showEstadoForm && (
                    <form onSubmit={handleCambiarEstado} className="card bg-base-200 p-4 space-y-3">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <FormField label="Nuevo Estado" htmlFor="estado" error={estadoForm.errors.estado} required>
                                <Select value={estadoForm.data.estado} onValueChange={(v) => estadoForm.setData('estado', v)} placeholder="Seleccionar">
                                    {estadosSiguientes.map((e) => (
                                        <SelectItem key={e} value={e}>{COB_ESTIMACION_ESTADO_LABELS[e as CobEstimacionEstado] ?? e}</SelectItem>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Fecha" htmlFor="fecha_cambio" error={estadoForm.errors.fecha_cambio}>
                                <Input type="date" value={estadoForm.data.fecha_cambio} onChange={(e) => estadoForm.setData('fecha_cambio', e.target.value)} />
                            </FormField>
                            <FormField label="Folio" htmlFor="folio_estado" error={estadoForm.errors.folio}>
                                <Input value={estadoForm.data.folio} onChange={(e) => estadoForm.setData('folio', e.target.value)} />
                            </FormField>
                            <FormField label="Comentario" htmlFor="comentario" error={estadoForm.errors.comentario}>
                                <Input value={estadoForm.data.comentario} onChange={(e) => estadoForm.setData('comentario', e.target.value)} />
                            </FormField>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" type="button" onClick={() => setShowEstadoForm(false)}>Cancelar</Button>
                            <Button size="sm" type="submit" disabled={estadoForm.processing}>Cambiar</Button>
                        </div>
                    </form>
                )}

                {/* Edit form */}
                <div className="card bg-base-100 border p-6">
                    <h2 className="text-lg font-semibold mb-4">Datos de la Estimacion</h2>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Folio" htmlFor="folio" error={form.errors.folio}>
                                <Input value={form.data.folio} onChange={(e) => form.setData('folio', e.target.value)} />
                            </FormField>
                            <FormField label="Tipo" htmlFor="tipo" error={form.errors.tipo}>
                                <Input value={form.data.tipo} onChange={(e) => form.setData('tipo', e.target.value)} />
                            </FormField>
                            <FormField label="Fecha Emision" htmlFor="fecha_emision" error={form.errors.fecha_emision}>
                                <Input type="date" value={form.data.fecha_emision} onChange={(e) => form.setData('fecha_emision', e.target.value)} />
                            </FormField>
                            <FormField label="Inicio" htmlFor="inicio" error={form.errors.inicio}>
                                <Input type="date" value={form.data.inicio} onChange={(e) => form.setData('inicio', e.target.value)} />
                            </FormField>
                            <FormField label="Fin" htmlFor="fin" error={form.errors.fin}>
                                <Input type="date" value={form.data.fin} onChange={(e) => form.setData('fin', e.target.value)} />
                            </FormField>
                            <FormField label="Monto Estimado" htmlFor="monto_estimado" error={form.errors.monto_estimado} required>
                                <Input type="number" step="0.01" value={form.data.monto_estimado} onChange={(e) => form.setData('monto_estimado', e.target.value)} />
                            </FormField>
                            <FormField label="Monto Total" htmlFor="monto_total" error={form.errors.monto_total}>
                                <Input type="number" step="0.01" value={form.data.monto_total} onChange={(e) => form.setData('monto_total', e.target.value)} />
                            </FormField>
                        </div>
                        <FormField label="Comentarios" htmlFor="comentarios" error={form.errors.comentarios}>
                            <textarea className="textarea textarea-bordered w-full" value={form.data.comentarios} onChange={(e) => form.setData('comentarios', e.target.value)} rows={3} />
                        </FormField>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild><Link href={`/admin/cob/obras/${obra.id}`}>Volver</Link></Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Pagos section */}
                <div className="card bg-base-100 border p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold">Pagos ({formatearMXN(estimacion.monto_pagado)} / {formatearMXN(estimacion.monto_estimado)})</h2>
                        {['facturada', 'pago_parcial'].includes(estimacion.estado) && (
                            <Button onClick={() => setShowPagoForm(!showPagoForm)} className="bg-green-600 hover:bg-green-700 text-white">
                                + Registrar Pago
                            </Button>
                        )}
                    </div>

                    {showPagoForm && (
                        <form onSubmit={handleRegistrarPago} className="card bg-base-200 p-4 mb-4 space-y-3">
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <FormField label="Monto" htmlFor="monto_pagado" error={pagoForm.errors.monto_pagado} required>
                                    <Input type="number" step="0.01" value={pagoForm.data.monto_pagado} onChange={(e) => pagoForm.setData('monto_pagado', e.target.value)} />
                                </FormField>
                                <FormField label="Fecha Pago" htmlFor="fecha_pago" error={pagoForm.errors.fecha_pago} required>
                                    <Input type="date" value={pagoForm.data.fecha_pago} onChange={(e) => pagoForm.setData('fecha_pago', e.target.value)} />
                                </FormField>
                                <FormField label="Folio" htmlFor="folio_pago" error={pagoForm.errors.folio}>
                                    <Input value={pagoForm.data.folio} onChange={(e) => pagoForm.setData('folio', e.target.value)} />
                                </FormField>
                                <FormField label="Comprobante" htmlFor="comprobante" error={pagoForm.errors.comprobante}>
                                    <input
                                        type="file"
                                        className="file-input file-input-bordered w-full"
                                        onChange={(e) => pagoForm.setData('comprobante', e.target.files?.[0] ?? null)}
                                    />
                                </FormField>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" size="sm" type="button" onClick={() => setShowPagoForm(false)}>Cancelar</Button>
                                <Button size="sm" type="submit" disabled={pagoForm.processing}>Registrar</Button>
                            </div>
                        </form>
                    )}

                    <table className="table table-sm">
                        <thead><tr><th>Fecha</th><th>Folio</th><th className="text-right">Monto</th><th>Comprobante</th></tr></thead>
                        <tbody>
                            {(estimacion.pagos ?? []).map((p) => (
                                <tr key={p.id}>
                                    <td>{new Date(p.fecha_pago).toLocaleDateString('es-MX')}</td>
                                    <td>{p.folio ?? '-'}</td>
                                    <td className="text-right">{formatearMXN(p.monto_pagado)}</td>
                                    <td>
                                        {p.comprobante ? (
                                            <a href={`/storage/${p.comprobante}`} target="_blank" rel="noopener noreferrer" className="link link-primary">
                                                Ver archivo
                                            </a>
                                        ) : '-'}
                                    </td>
                                </tr>
                            ))}
                            {(estimacion.pagos ?? []).length === 0 && (
                                <tr><td colSpan={4} className="text-center opacity-50">No hay pagos registrados</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Historial */}
                <div className="card bg-base-100 border p-6">
                    <h2 className="text-lg font-semibold mb-4">Historial de Estados</h2>
                    <table className="table table-sm">
                        <thead><tr><th>Fecha</th><th>De</th><th>A</th><th>Folio</th><th>Usuario</th><th>Comentario</th></tr></thead>
                        <tbody>
                            {(estimacion.historial ?? []).map((h) => (
                                <tr key={h.id}>
                                    <td>{new Date(h.fecha_cambio).toLocaleString('es-MX')}</td>
                                    <td>{h.estado_anterior ?? '-'}</td>
                                    <td>{COB_ESTIMACION_ESTADO_LABELS[h.estado_nuevo as CobEstimacionEstado] ?? h.estado_nuevo}</td>
                                    <td>{h.folio ?? '-'}</td>
                                    <td>{h.usuario?.name ?? '-'}</td>
                                    <td>{h.comentario ?? '-'}</td>
                                </tr>
                            ))}
                            {(estimacion.historial ?? []).length === 0 && (
                                <tr><td colSpan={6} className="text-center opacity-50">Sin historial</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
