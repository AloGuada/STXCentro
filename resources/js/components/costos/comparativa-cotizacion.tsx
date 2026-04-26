import { Button } from '@/components/ui/button';
import type { CostosRequisicion, CostosRequisicionCotizacionPrecio, CostosRequisicionDetalle, Proveedor } from '@/types/models';
import { router } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

type Props = {
    requisicion: CostosRequisicion;
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>[];
    editable: boolean;
};

/**
 * Cuadro comparativo de cotizacion: filas = partidas, columnas = proveedores.
 * Compras agrega proveedores y captura precio_unitario por celda. Cada celda
 * con precio se convierte en una "RequisicionCotizacionPrecio" en backend.
 */
export function ComparativaCotizacion({ requisicion, proveedores, editable }: Props) {
    const detalles = requisicion.detalles ?? [];

    // Proveedores presentes en al menos una cotizacion existente
    const proveedoresUsados = useMemo(() => {
        const ids = new Set<number>();
        detalles.forEach((d) => d.cotizaciones?.forEach((c) => ids.add(c.proveedor_id)));
        return Array.from(ids);
    }, [detalles]);

    const [proveedoresActivos, setProveedoresActivos] = useState<number[]>(proveedoresUsados);
    const [agregarOpen, setAgregarOpen] = useState(false);
    const [proveedorNuevo, setProveedorNuevo] = useState<number | ''>('');

    const proveedoresMap = useMemo(() => {
        const m = new Map<number, Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    const proveedoresDisponibles = proveedores.filter((p) => !proveedoresActivos.includes(p.id));

    const agregarProveedor = () => {
        if (proveedorNuevo === '') return;
        setProveedoresActivos([...proveedoresActivos, Number(proveedorNuevo)]);
        setProveedorNuevo('');
        setAgregarOpen(false);
    };

    const quitarProveedor = (proveedorId: number) => {
        if (!confirm('¿Quitar este proveedor del cuadro? Los precios capturados también se eliminarán.')) return;
        // Borrar todos los precios de este proveedor en backend
        detalles.forEach((d) => {
            const precio = d.cotizaciones?.find((c) => c.proveedor_id === proveedorId);
            if (precio) {
                router.delete(`/admin/costos/requisiciones/cotizaciones/${precio.id}`, {
                    preserveScroll: true,
                });
            }
        });
        setProveedoresActivos(proveedoresActivos.filter((id) => id !== proveedorId));
    };

    return (
        <div className="rounded-lg border border-base-300 p-4">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="font-medium">Cuadro comparativo</h3>
                {editable && (
                    <div className="flex items-center gap-2">
                        {agregarOpen ? (
                            <>
                                <select
                                    className="select select-bordered select-sm"
                                    value={proveedorNuevo}
                                    onChange={(e) => setProveedorNuevo(e.target.value ? Number(e.target.value) : '')}
                                >
                                    <option value="">Selecciona proveedor</option>
                                    {proveedoresDisponibles.map((p) => (
                                        <option key={p.id} value={p.id}>{p.razon_social}</option>
                                    ))}
                                </select>
                                <Button onClick={agregarProveedor} disabled={!proveedorNuevo}>Agregar</Button>
                                <Button variant="outline" onClick={() => setAgregarOpen(false)}>Cancelar</Button>
                            </>
                        ) : (
                            <Button variant="outline" onClick={() => setAgregarOpen(true)}>
                                <PlusIcon className="size-3.5" /> Agregar proveedor
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {proveedoresActivos.length === 0 ? (
                <p className="py-4 text-center text-sm text-base-content/60">
                    Aún no hay proveedores en el cuadro. Agrega uno para comenzar a cotizar.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th className="min-w-[200px]">Partida</th>
                                <th className="text-right w-24">Cantidad</th>
                                {proveedoresActivos.map((pid) => (
                                    <th key={pid} className="min-w-[180px]">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs">{proveedoresMap.get(pid)?.razon_social ?? `Proveedor ${pid}`}</span>
                                            {editable && (
                                                <button
                                                    type="button"
                                                    onClick={() => quitarProveedor(pid)}
                                                    className="text-error opacity-60 hover:opacity-100"
                                                    title="Quitar proveedor"
                                                >
                                                    <Trash2Icon className="size-3" />
                                                </button>
                                            )}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((detalle) => (
                                <tr key={detalle.id}>
                                    <td>
                                        <div className="text-sm">{detalle.descripcion}</div>
                                        <div className="text-xs text-base-content/60">{detalle.unidad}</div>
                                    </td>
                                    <td className="text-right">{Number(detalle.cantidad).toLocaleString('es-MX')}</td>
                                    {proveedoresActivos.map((pid) => {
                                        const precio = detalle.cotizaciones?.find((c) => c.proveedor_id === pid);
                                        return (
                                            <td key={pid}>
                                                <PrecioCell
                                                    detalle={detalle}
                                                    proveedorId={pid}
                                                    precio={precio}
                                                    editable={editable}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function PrecioCell({
    detalle,
    proveedorId,
    precio,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    proveedorId: number;
    precio?: CostosRequisicionCotizacionPrecio;
    editable: boolean;
}) {
    const [precioInput, setPrecioInput] = useState<string>(precio ? String(precio.precio_unitario) : '');
    const [tiempo, setTiempo] = useState<string>(precio?.tiempo_entrega_dias ? String(precio.tiempo_entrega_dias) : '');
    const [observaciones, setObservaciones] = useState<string>(precio?.observaciones ?? '');
    const [saving, setSaving] = useState(false);

    const guardar = () => {
        const precioNum = Number(precioInput);
        if (!Number.isFinite(precioNum) || precioNum <= 0) return;

        setSaving(true);
        router.post('/admin/costos/requisiciones/cotizaciones', {
            requisicion_detalle_id: detalle.id,
            proveedor_id: proveedorId,
            precio_unitario: precioNum,
            tiempo_entrega_dias: tiempo ? Number(tiempo) : null,
            observaciones: observaciones || null,
        }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    if (!editable) {
        return precio ? (
            <div className="text-sm">
                <div className="font-medium">${Number(precio.precio_unitario).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                {precio.tiempo_entrega_dias != null && (
                    <div className="text-xs text-base-content/60">{precio.tiempo_entrega_dias} días</div>
                )}
                {precio.observaciones && (
                    <div className="text-xs text-base-content/60">{precio.observaciones}</div>
                )}
            </div>
        ) : (
            <span className="text-base-content/40">—</span>
        );
    }

    return (
        <div className="space-y-1">
            <input
                type="number"
                step="0.01"
                placeholder="Precio"
                className="input input-bordered input-sm w-full"
                value={precioInput}
                onChange={(e) => setPrecioInput(e.target.value)}
                onBlur={guardar}
            />
            <input
                type="number"
                placeholder="Días"
                className="input input-bordered input-xs w-full"
                value={tiempo}
                onChange={(e) => setTiempo(e.target.value)}
                onBlur={guardar}
            />
            <input
                type="text"
                placeholder="Notas"
                className="input input-bordered input-xs w-full"
                value={observaciones}
                onChange={(e) => setObservaciones(e.target.value)}
                onBlur={guardar}
            />
            {saving && <div className="text-xs text-base-content/40">Guardando...</div>}
        </div>
    );
}
