import { router, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CostosEntrega, CostosOrdenCompra, CostosOrdenCompraDetalle } from '@/types/models';

type Props = {
    open: boolean;
    onClose: () => void;
    ordenCompra: CostosOrdenCompra;
};

type DetalleRow = {
    orden_compra_detalle_id: number;
    cantidad_recibida: string;
    precio_unitario: string;
    observaciones: string;
};

const fmt = (n: number) =>
    `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

function saldoDePartida(ocd: CostosOrdenCompraDetalle, entregas: CostosEntrega[] | undefined): number {
    const yaRecibido = (entregas ?? [])
        .filter((e) => !e.cancelada_at)
        .flatMap((e) => e.detalles ?? [])
        .filter((d) => d.orden_compra_detalle_id === ocd.id)
        .reduce((acc, d) => acc + Number(d.cantidad_recibida), 0);
    return Number(ocd.cantidad) - yaRecibido;
}

export function EntregaModal({ open, onClose, ordenCompra }: Props) {
    const partidas = ordenCompra.detalles ?? [];

    const saldos = useMemo(() => {
        const map: Record<number, number> = {};
        for (const p of partidas) {
            map[p.id] = saldoDePartida(p, ordenCompra.entregas);
        }
        return map;
    }, [partidas, ordenCompra.entregas]);

    const facturasPendientes = (ordenCompra.facturas ?? []).filter((f) => f.estatus === 'pendiente_recepcion');

    const { data, setData, processing, errors, reset } = useForm<{
        fecha_entrega: string;
        tipo: 'parcial' | 'completa';
        factura_id: string;
        completa_factura: boolean;
        observaciones: string;
        archivo: File | null;
        detalles: DetalleRow[];
    }>({
        fecha_entrega: new Date().toISOString().split('T')[0],
        tipo: 'parcial',
        factura_id: '',
        completa_factura: false,
        observaciones: '',
        archivo: null,
        detalles: partidas.map((p) => ({
            orden_compra_detalle_id: p.id,
            cantidad_recibida: '',
            precio_unitario: String(Number(p.precio_unitario)),
            observaciones: '',
        })),
    });

    const importeLinea = (d: DetalleRow): number =>
        (parseFloat(d.cantidad_recibida) || 0) * (parseFloat(d.precio_unitario) || 0);

    const subtotalRecepcion = useMemo(
        () => data.detalles.reduce((acc, d) => acc + importeLinea(d), 0),
        [data.detalles],
    );

    if (!open) {
        return null;
    }

    const updateDetalle = (idx: number, field: keyof DetalleRow, value: string) => {
        const updated = [...data.detalles];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('detalles', updated);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Filtrar solo partidas con cantidad > 0
        const filled = data.detalles
            .map((d) => ({ ...d, cantidad_recibida: parseFloat(d.cantidad_recibida) || 0 }))
            .filter((d) => d.cantidad_recibida > 0);

        router.post(
            `/admin/costos/ordenes-compra/${ordenCompra.id}/entregas`,
            {
                fecha_entrega: data.fecha_entrega,
                tipo: data.tipo,
                factura_id: data.factura_id || null,
                completa_factura: data.completa_factura,
                observaciones: data.observaciones,
                archivo: data.archivo,
                detalles: filled,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onClose();
                },
            },
        );
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-5xl">
                <h3 className="font-bold text-lg mb-4">Registrar entrega</h3>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label="Fecha de entrega" htmlFor="fecha_entrega" error={errors.fecha_entrega} required>
                            <Input
                                id="fecha_entrega"
                                type="date"
                                value={data.fecha_entrega}
                                onChange={(e) => setData('fecha_entrega', e.target.value)}
                            />
                        </FormField>
                        <FormField label="Tipo" htmlFor="tipo" error={errors.tipo} required>
                            <select
                                id="tipo"
                                className="select select-bordered w-full"
                                value={data.tipo}
                                onChange={(e) => setData('tipo', e.target.value as 'parcial' | 'completa')}
                            >
                                <option value="parcial">Parcial</option>
                                <option value="completa">Completa</option>
                            </select>
                        </FormField>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <FormField label="Factura a ligar (opcional)" htmlFor="factura_id" error={errors.factura_id}>
                            <select
                                id="factura_id"
                                className="select select-bordered w-full"
                                value={data.factura_id}
                                onChange={(e) => setData({ ...data, factura_id: e.target.value, completa_factura: e.target.value ? data.completa_factura : false })}
                            >
                                <option value="">— Sin factura —</option>
                                {facturasPendientes.map((f) => (
                                    <option key={f.id} value={String(f.id)}>
                                        {f.folio} · {fmt(Number(f.total))}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <div className="flex items-end pb-2">
                            <label className="label cursor-pointer justify-start gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox"
                                    disabled={!data.factura_id}
                                    checked={data.completa_factura}
                                    onChange={(e) => setData('completa_factura', e.target.checked)}
                                />
                                <span className="label-text">Esta entrega completa la factura</span>
                            </label>
                        </div>
                    </div>

                    <FormField label="Observaciones" htmlFor="observaciones" error={errors.observaciones}>
                        <textarea
                            id="observaciones"
                            className="textarea textarea-bordered w-full"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            rows={2}
                        />
                    </FormField>

                    <FormField label="Documento (evidencia)" htmlFor="archivo" error={errors.archivo}>
                        <input
                            id="archivo"
                            type="file"
                            className="file-input file-input-bordered file-input-sm w-full"
                            onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <div>
                        <div className="text-sm font-medium mb-2">Partidas recibidas</div>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Partida</th>
                                        <th className="text-right">Ordenado</th>
                                        <th className="text-right">Por recibir</th>
                                        <th className="w-28">Recibir ahora</th>
                                        <th className="text-right">P.U. OC</th>
                                        <th className="w-28">P.U. recibido</th>
                                        <th className="text-right">Importe</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {partidas.map((p, idx) => {
                                        const saldo = saldos[p.id] ?? 0;
                                        const errorKey = `detalles.${idx}.cantidad_recibida` as const;
                                        const err = errors[errorKey as keyof typeof errors];

                                        return (
                                            <tr key={p.id}>
                                                <td>
                                                    <div className="font-medium">{p.descripcion}</div>
                                                    <div className="text-xs text-base-content/60">{p.unidad}</div>
                                                </td>
                                                <td className="text-right">{Number(p.cantidad).toLocaleString('es-MX')}</td>
                                                <td className={`text-right ${saldo <= 0 ? 'text-success' : ''}`}>
                                                    {saldo.toLocaleString('es-MX')}
                                                </td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max={saldo}
                                                        disabled={saldo <= 0}
                                                        value={data.detalles[idx]?.cantidad_recibida ?? ''}
                                                        onChange={(e) => updateDetalle(idx, 'cantidad_recibida', e.target.value)}
                                                    />
                                                    {err && <div className="text-xs text-error mt-1">{err}</div>}
                                                </td>
                                                <td className="text-right text-base-content/60">{fmt(Number(p.precio_unitario))}</td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={data.detalles[idx]?.precio_unitario ?? ''}
                                                        onChange={(e) => updateDetalle(idx, 'precio_unitario', e.target.value)}
                                                    />
                                                </td>
                                                <td className="text-right font-medium">
                                                    {fmt(importeLinea(data.detalles[idx] ?? { cantidad_recibida: '', precio_unitario: '', observaciones: '', orden_compra_detalle_id: p.id }))}
                                                </td>
                                                <td>
                                                    <Input
                                                        value={data.detalles[idx]?.observaciones ?? ''}
                                                        onChange={(e) => updateDetalle(idx, 'observaciones', e.target.value)}
                                                        placeholder="Opcional"
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colSpan={6} className="text-right font-medium">Subtotal</td>
                                        <td className="text-right font-semibold">{fmt(subtotalRecepcion)}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        {errors.detalles && <p className="text-sm text-error mt-2">{errors.detalles}</p>}
                    </div>

                    <div className="modal-action">
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Guardar entrega
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={processing ? undefined : onClose}></div>
        </dialog>
    );
}
