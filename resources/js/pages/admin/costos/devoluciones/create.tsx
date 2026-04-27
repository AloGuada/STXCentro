import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

type EntregaDetalleOption = {
    id: number;
    oc_folio: string | null;
    oc_id: number | null;
    proveedor: string | null;
    fecha_entrega: string | null;
    partida_descripcion: string | null;
    unidad: string | null;
    cantidad_recibida: number;
    cantidad_disponible: number;
};

type FormData = {
    entrega_detalle_id: number | '';
    cantidad: number;
    motivo: string;
    fecha: string;
    evidencia: File | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/devoluciones' },
    { title: 'Devoluciones', href: '/admin/costos/devoluciones' },
    { title: 'Nueva', href: '/admin/costos/devoluciones/create' },
];

type Props = {
    entregaDetalles: EntregaDetalleOption[];
    preselectId: number | null;
};

export default function DevolucionesCreate({ entregaDetalles, preselectId }: Props) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        entrega_detalle_id: preselectId ?? '',
        cantidad: 1,
        motivo: '',
        fecha: new Date().toISOString().slice(0, 10),
        evidencia: null,
    });

    const seleccionada = useMemo(
        () => entregaDetalles.find((e) => e.id === data.entrega_detalle_id),
        [entregaDetalles, data.entrega_detalle_id],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/costos/devoluciones', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva devolución" />

            <form onSubmit={handleSubmit} className="p-6 max-w-3xl">
                <h1 className="mb-4 text-2xl font-semibold">Registrar devolución</h1>

                {entregaDetalles.length === 0 ? (
                    <p className="alert alert-info">
                        No hay partidas recibidas con saldo disponible para devolución.
                    </p>
                ) : (
                    <div className="space-y-4">
                        <div>
                            <label className="label label-text">Partida recibida *</label>
                            <select
                                className="select select-bordered w-full"
                                value={data.entrega_detalle_id}
                                onChange={(e) => setData('entrega_detalle_id', e.target.value ? Number(e.target.value) : '')}
                            >
                                <option value="">Selecciona una partida...</option>
                                {entregaDetalles.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.oc_folio} · {e.proveedor} · {e.partida_descripcion} · disponible {e.cantidad_disponible.toLocaleString('es-MX')} {e.unidad}
                                    </option>
                                ))}
                            </select>
                            {errors.entrega_detalle_id && <p className="text-error text-sm mt-1">{errors.entrega_detalle_id}</p>}
                        </div>

                        {seleccionada && (
                            <div className="alert alert-info text-sm">
                                Recibido: {seleccionada.cantidad_recibida.toLocaleString('es-MX')} {seleccionada.unidad}
                                {' · '}
                                Disponible para devolver: <strong>{seleccionada.cantidad_disponible.toLocaleString('es-MX')}</strong>
                                {seleccionada.fecha_entrega && (
                                    <> · Fecha entrega: {seleccionada.fecha_entrega}</>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="label label-text">Cantidad a devolver *</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    max={seleccionada?.cantidad_disponible ?? undefined}
                                    className="input input-bordered w-full"
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', Number(e.target.value))}
                                />
                                {errors.cantidad && <p className="text-error text-sm mt-1">{errors.cantidad}</p>}
                            </div>
                            <div>
                                <label className="label label-text">Fecha *</label>
                                <input
                                    type="date"
                                    className="input input-bordered w-full"
                                    value={data.fecha}
                                    onChange={(e) => setData('fecha', e.target.value)}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="label label-text">Motivo *</label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                rows={3}
                                value={data.motivo}
                                onChange={(e) => setData('motivo', e.target.value)}
                                placeholder="Describe la condición física, defecto, error de envío, etc."
                            />
                            {errors.motivo && <p className="text-error text-sm mt-1">{errors.motivo}</p>}
                        </div>

                        <div>
                            <label className="label label-text">Evidencia (opcional)</label>
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                className="file-input file-input-bordered w-full"
                                onChange={(e) => setData('evidencia', e.target.files?.[0] ?? null)}
                            />
                        </div>

                        <div className="flex justify-end gap-2 mt-6">
                            <Button type="button" variant="outline" asChild>
                                <a href="/admin/costos/devoluciones">Cancelar</a>
                            </Button>
                            <Button type="submit" disabled={processing || data.entrega_detalle_id === ''}>
                                {processing ? 'Guardando...' : 'Registrar devolución'}
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
