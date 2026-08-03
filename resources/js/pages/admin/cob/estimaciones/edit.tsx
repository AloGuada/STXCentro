import { Head, Link, useForm } from '@inertiajs/react';
import { FileIcon, Loader2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fechaParaInput, formatFecha, formatFechaHora } from '@/lib/fechas';
import type { BreadcrumbItem } from '@/types';
import {
    COB_ESTIMACION_ESTADO_LABELS,
    type CobEstimacion,
    type CobEstimacionEstado,
    type CobEstimacionNivel,
    type CobPartida,
    type CobTipoRetencion,
    type Obra,
    type Proyecto,
} from '@/types/models';

type ObraConPartidas = Pick<Obra, 'id' | 'no' | 'descripcion'> & { partidas: Pick<CobPartida, 'id' | 'descripcion' | 'tipo' | 'monto'>[] };

type Props = {
    proyecto: Pick<Proyecto, 'id' | 'no' | 'descripcion'>;
    obras: ObraConPartidas[];
    estimacion: CobEstimacion;
    partidaIds: number[];
    tiposRetencion: CobTipoRetencion[];
};

export default function EstimacionEdit({ proyecto, obras, estimacion, partidaIds }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: `Proyecto ${proyecto.no}`, href: `/admin/cob/proyectos/${proyecto.id}` },
        { title: `Estimación #${estimacion.numero_estimacion}`, href: '#' },
    ];


    const form = useForm({
        nivel: estimacion.nivel as CobEstimacionNivel,
        obra_id: estimacion.obra_id ? String(estimacion.obra_id) : '',
        partida_ids: partidaIds ?? [],
        folio: estimacion.folio ?? '',
        tipo: estimacion.tipo ?? '',
        fecha_emision: fechaParaInput(estimacion.fecha_emision),
        inicio: fechaParaInput(estimacion.inicio),
        fin: fechaParaInput(estimacion.fin),
        monto_estimado: String(estimacion.monto_estimado),
        monto_total: String(estimacion.monto_total),
        moneda: estimacion.moneda,
        comentarios: estimacion.comentarios ?? '',
    });

    const obraSel = obras.find((o) => String(o.id) === form.data.obra_id);

    const cambiarNivel = (nivel: CobEstimacionNivel) => {
        form.setData((prev) => ({
            ...prev,
            nivel,
            obra_id: nivel === 'proyecto' ? '' : prev.obra_id,
            partida_ids: nivel === 'partida' ? prev.partida_ids : [],
        }));
    };

    const togglePartida = (id: number) => {
        form.setData('partida_ids', form.data.partida_ids.includes(id) ? form.data.partida_ids.filter((p) => p !== id) : [...form.data.partida_ids, id]);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/proyectos/${proyecto.id}/estimaciones/${estimacion.id}`);
    };

    const [showEstadoForm, setShowEstadoForm] = useState(false);
    const estadoForm = useForm({ estado: '', fecha_cambio: '', folio: '', comentario: '' });

    const handleCambiarEstado = (e: FormEvent) => {
        e.preventDefault();
        estadoForm.post(`/admin/cob/proyectos/${proyecto.id}/estimaciones/${estimacion.id}/cambiar-estado`, {
            onSuccess: () => {
                setShowEstadoForm(false);
                estadoForm.reset();
            },
        });
    };

    const transiciones: Record<string, string[]> = {
        ingresada: ['autorizada'],
        autorizada: ['facturada'],
        facturada: ['pago_parcial', 'pagado'],
        pago_parcial: ['pagado'],
        pagado: [],
    };

    const estadosSiguientes = transiciones[estimacion.estado] ?? [];

    const [showPagoForm, setShowPagoForm] = useState(false);
    const pagoForm = useForm({ monto_pagado: '', fecha_pago: '', folio: '', comprobantes: [] as File[] });

    const handleRegistrarPago = (e: FormEvent) => {
        e.preventDefault();
        pagoForm.post(`/admin/cob/proyectos/${proyecto.id}/estimaciones/${estimacion.id}/pagos`, {
            forceFormData: true,
            onSuccess: () => {
                setShowPagoForm(false);
                pagoForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Estimación #${estimacion.numero_estimacion}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Estimación #{estimacion.numero_estimacion}</h1>
                        <p className="text-sm opacity-70">Proyecto {proyecto.no} · {proyecto.descripcion}</p>
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

                {showEstadoForm && (
                    <form onSubmit={handleCambiarEstado} className="card bg-base-200 space-y-3 p-4">
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

                <div className="card bg-base-100 border p-6">
                    <h2 className="mb-4 text-lg font-semibold">Datos de la Estimación</h2>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Nivel" htmlFor="nivel" error={form.errors.nivel}>
                                <select className="select select-bordered w-full" value={form.data.nivel} onChange={(e) => cambiarNivel(e.target.value as CobEstimacionNivel)}>
                                    <option value="proyecto">Global (todo el proyecto)</option>
                                    <option value="obra">Una obra</option>
                                    <option value="partida">Partidas de una obra</option>
                                </select>
                            </FormField>
                            {form.data.nivel !== 'proyecto' && (
                                <FormField label="Obra" htmlFor="obra_id" error={form.errors.obra_id} required>
                                    <select
                                        className="select select-bordered w-full"
                                        value={form.data.obra_id}
                                        onChange={(e) => form.setData((prev) => ({ ...prev, obra_id: e.target.value, partida_ids: [] }))}
                                    >
                                        <option value="">Seleccionar obra</option>
                                        {obras.map((o) => (
                                            <option key={o.id} value={o.id}>{o.no} — {o.descripcion}</option>
                                        ))}
                                    </select>
                                </FormField>
                            )}
                        </div>

                        {form.data.nivel === 'partida' && obraSel && (
                            <FormField label="Partidas" htmlFor="partida_ids" error={form.errors.partida_ids}>
                                <div className="rounded-box max-h-48 space-y-1 overflow-auto border border-base-300 p-2">
                                    {(obraSel.partidas ?? []).map((p) => (
                                        <label key={p.id} className="flex cursor-pointer items-center gap-2 text-sm">
                                            <input type="checkbox" className="checkbox checkbox-sm" checked={form.data.partida_ids.includes(p.id)} onChange={() => togglePartida(p.id)} />
                                            <span className="capitalize">{p.tipo}</span>
                                            <span className="flex-1">{p.descripcion}</span>
                                            <span className="opacity-60">{formatearMXN(Number(p.monto))}</span>
                                        </label>
                                    ))}
                                    {(obraSel.partidas ?? []).length === 0 && <p className="text-sm opacity-50">La obra no tiene partidas.</p>}
                                </div>
                            </FormField>
                        )}

                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Folio" htmlFor="folio" error={form.errors.folio}>
                                <Input value={form.data.folio} onChange={(e) => form.setData('folio', e.target.value)} />
                            </FormField>
                            <FormField label="Tipo" htmlFor="tipo" error={form.errors.tipo}>
                                <Input value={form.data.tipo} onChange={(e) => form.setData('tipo', e.target.value)} />
                            </FormField>
                            <FormField label="Fecha Emisión" htmlFor="fecha_emision" error={form.errors.fecha_emision}>
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
                            <Button variant="outline" asChild><Link href={`/admin/cob/proyectos/${proyecto.id}`}>Volver</Link></Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Pagos */}
                <div className="card bg-base-100 border p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Pagos ({formatearMXN(estimacion.monto_pagado)} / {formatearMXN(estimacion.monto_estimado)})</h2>
                        {['facturada', 'pago_parcial'].includes(estimacion.estado) && (
                            <Button onClick={() => setShowPagoForm(!showPagoForm)} className="bg-green-600 text-white hover:bg-green-700">
                                + Registrar Pago
                            </Button>
                        )}
                    </div>

                    {showPagoForm && (
                        <form onSubmit={handleRegistrarPago} className="card bg-base-200 mb-4 space-y-3 p-4">
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
                                <FormField label="Comprobantes" htmlFor="comprobantes" error={pagoForm.errors.comprobantes}>
                                    <input
                                        type="file"
                                        multiple
                                        className="file-input file-input-bordered w-full"
                                        onChange={(e) => pagoForm.setData('comprobantes', Array.from(e.target.files ?? []))}
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
                                    <td>{formatFecha(p.fecha_pago)}</td>
                                    <td>{p.folio ?? '-'}</td>
                                    <td className="text-right">{formatearMXN(p.monto_pagado)}</td>
                                    <td>
                                        {(p.comprobantes ?? []).length > 0 ? (
                                            <div className="flex flex-wrap items-center gap-2">
                                                {(p.comprobantes ?? []).map((m, i) => (
                                                    <a
                                                        key={m.id}
                                                        href={`/storage/${m.path}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="link link-primary inline-flex items-center gap-1"
                                                        title={m.nombre_original ?? `Comprobante ${i + 1}`}
                                                    >
                                                        <FileIcon className="size-3.5" />
                                                        {m.nombre_original ?? `Archivo ${i + 1}`}
                                                    </a>
                                                ))}
                                            </div>
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
                    <h2 className="mb-4 text-lg font-semibold">Historial de Estados</h2>
                    <table className="table table-sm">
                        <thead><tr><th>Fecha</th><th>De</th><th>A</th><th>Folio</th><th>Usuario</th><th>Comentario</th></tr></thead>
                        <tbody>
                            {(estimacion.historial ?? []).map((h) => (
                                <tr key={h.id}>
                                    <td>{formatFechaHora(h.fecha_cambio)}</td>
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
