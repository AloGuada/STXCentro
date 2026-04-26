import { Button } from '@/components/ui/button';
import type { CostosRequisicion, CostosRequisicionCotizacionPrecio, CostosRequisicionDetalle } from '@/types/models';
import { router } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    requisicion: CostosRequisicion;
    editable: boolean;
};

/**
 * Selección de distribución por proveedor: para cada partida, asigna
 * cantidades a los proveedores que cotizaron. Permite split: una partida
 * puede repartirse entre varios proveedores. La suma de selecciones no
 * puede exceder la cantidad solicitada.
 */
export function SeleccionDistribucion({ requisicion, editable }: Props) {
    const detalles = requisicion.detalles ?? [];

    return (
        <div className="rounded-lg border border-base-300 p-4">
            <h3 className="mb-3 font-medium">Distribución final por proveedor</h3>
            <div className="space-y-4">
                {detalles.map((detalle) => (
                    <DetalleSelecciones
                        key={detalle.id}
                        detalle={detalle}
                        editable={editable}
                    />
                ))}
            </div>
        </div>
    );
}

function DetalleSelecciones({
    detalle,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    editable: boolean;
}) {
    const cotizaciones = detalle.cotizaciones ?? [];
    const selecciones = detalle.selecciones ?? [];
    const cantidadTotal = Number(detalle.cantidad);
    const cantidadAsignada = selecciones.reduce((sum, s) => sum + Number(s.cantidad), 0);
    const restante = Math.max(0, cantidadTotal - cantidadAsignada);
    const cubierta = Math.abs(restante) < 0.001;

    return (
        <div className="rounded border border-base-200 p-3">
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <div className="text-sm font-medium">{detalle.descripcion}</div>
                    <div className="text-xs text-base-content/60">
                        Solicitado: {cantidadTotal.toLocaleString('es-MX')} {detalle.unidad}
                        {' · '}
                        Asignado: {cantidadAsignada.toLocaleString('es-MX')}
                        {' · '}
                        Restante: {restante.toLocaleString('es-MX')}
                    </div>
                </div>
                <span className={`badge badge-sm ${cubierta ? 'badge-success' : 'badge-warning'}`}>
                    {cubierta ? 'Cubierta' : 'Incompleta'}
                </span>
            </div>

            {selecciones.length > 0 && (
                <table className="table table-xs mb-2">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th className="text-right">Precio</th>
                            <th className="text-right">Cantidad</th>
                            <th className="text-right">Subtotal</th>
                            {editable && <th className="w-10"></th>}
                        </tr>
                    </thead>
                    <tbody>
                        {selecciones.map((s) => {
                            const precio = Number(s.cotizacion_precio?.precio_unitario ?? 0);
                            const cant = Number(s.cantidad);
                            return (
                                <tr key={s.id}>
                                    <td>{s.proveedor?.razon_social ?? `#${s.proveedor_id}`}</td>
                                    <td className="text-right">${precio.toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                    <td className="text-right">{cant.toLocaleString('es-MX')}</td>
                                    <td className="text-right">${(precio * cant).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                    {editable && (
                                        <td>
                                            <button
                                                type="button"
                                                onClick={() => router.delete(`/admin/costos/requisiciones/selecciones/${s.id}`, { preserveScroll: true })}
                                                className="btn btn-ghost btn-xs text-error"
                                            >
                                                <Trash2Icon className="size-3" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            )}

            {editable && !cubierta && cotizaciones.length > 0 && (
                <AgregarSeleccion
                    cotizaciones={cotizaciones}
                    restante={restante}
                />
            )}

            {editable && cotizaciones.length === 0 && (
                <p className="text-xs text-base-content/40 italic">
                    Captura precios en el cuadro comparativo antes de asignar proveedores.
                </p>
            )}
        </div>
    );
}

function AgregarSeleccion({
    cotizaciones,
    restante,
}: {
    cotizaciones: CostosRequisicionCotizacionPrecio[];
    restante: number;
}) {
    const [precioId, setPrecioId] = useState<number | ''>('');
    const [cantidad, setCantidad] = useState<string>(String(restante));
    const [submitting, setSubmitting] = useState(false);

    const submit = () => {
        if (precioId === '') return;
        const cantidadNum = Number(cantidad);
        if (!Number.isFinite(cantidadNum) || cantidadNum <= 0 || cantidadNum > restante + 0.001) return;

        setSubmitting(true);
        router.post('/admin/costos/requisiciones/selecciones', {
            cotizacion_precio_id: precioId,
            cantidad: cantidadNum,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPrecioId('');
                setCantidad(String(restante));
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="flex flex-wrap items-end gap-2">
            <div className="flex-1 min-w-[200px]">
                <label className="label-text text-xs">Proveedor</label>
                <select
                    className="select select-bordered select-sm w-full"
                    value={precioId}
                    onChange={(e) => setPrecioId(e.target.value ? Number(e.target.value) : '')}
                >
                    <option value="">Seleccionar...</option>
                    {cotizaciones.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.proveedor?.razon_social ?? `#${c.proveedor_id}`} — ${Number(c.precio_unitario).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                        </option>
                    ))}
                </select>
            </div>
            <div className="w-32">
                <label className="label-text text-xs">Cantidad</label>
                <input
                    type="number"
                    step="0.01"
                    max={restante}
                    className="input input-bordered input-sm w-full"
                    value={cantidad}
                    onChange={(e) => setCantidad(e.target.value)}
                />
            </div>
            <Button onClick={submit} disabled={submitting || precioId === ''}>
                {submitting ? '...' : 'Asignar'}
            </Button>
        </div>
    );
}
