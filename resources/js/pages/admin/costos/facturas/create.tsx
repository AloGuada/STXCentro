import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra, CostosOrdenCompraDetalle } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

type OrdenListItem = Pick<CostosOrdenCompra, 'id' | 'folio' | 'proveedor_id' | 'moneda' | 'total'> & {
    proveedor?: { id: number; razon_social: string };
};

type Props = {
    ordenes: OrdenListItem[];
    ordenCompra?: CostosOrdenCompra | null;
};

type DetalleRow = {
    orden_compra_detalle_id: number;
    cantidad: string;
    precio_unitario: string;
};

/**
 * Calcula cuanto ya se facturo de una partida en facturas ACTIVAS
 * (no canceladas). El backend repite esta validacion.
 */
function yaFacturado(ocd: CostosOrdenCompraDetalle, oc: CostosOrdenCompra | null | undefined): number {
    if (!oc?.facturas) {
        return 0;
    }
    return oc.facturas
        .filter((f) => f.estatus !== 'cancelada')
        .flatMap((f) => f.detalles ?? [])
        .filter((d) => d.orden_compra_detalle_id === ocd.id)
        .reduce((acc, d) => acc + Number(d.cantidad), 0);
}

export default function FacturasCreate({ ordenes, ordenCompra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/facturas' },
        { title: 'Facturas', href: '/admin/costos/facturas' },
        { title: 'Nueva', href: '/admin/costos/facturas/create' },
    ];

    const partidas = ordenCompra?.detalles ?? [];

    const saldosFacturables = useMemo(() => {
        const map: Record<number, number> = {};
        for (const p of partidas) {
            map[p.id] = Number(p.cantidad) - yaFacturado(p, ordenCompra);
        }
        return map;
    }, [partidas, ordenCompra]);

    const { data, setData, post, processing, errors } = useForm<{
        orden_compra_id: string;
        uuid_fiscal: string;
        folio_fiscal: string;
        fecha_factura: string;
        iva: string;
        moneda: string;
        notas: string;
        detalles: DetalleRow[];
    }>({
        orden_compra_id: ordenCompra ? String(ordenCompra.id) : '',
        uuid_fiscal: '',
        folio_fiscal: '',
        fecha_factura: new Date().toISOString().split('T')[0],
        iva: '0',
        moneda: ordenCompra?.moneda ?? 'mxn',
        notas: '',
        detalles: partidas.map((p) => ({
            orden_compra_detalle_id: p.id,
            cantidad: '',
            precio_unitario: String(p.precio_unitario),
        })),
    });

    const subtotalCalculado = useMemo(
        () =>
            data.detalles.reduce(
                (acc, d) => acc + (parseFloat(d.cantidad) || 0) * (parseFloat(d.precio_unitario) || 0),
                0,
            ),
        [data.detalles],
    );

    const totalCalculado = subtotalCalculado + (parseFloat(data.iva) || 0);

    const formatMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

    const updateDetalle = (idx: number, field: keyof DetalleRow, value: string) => {
        const updated = [...data.detalles];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('detalles', updated);
    };

    const switchOrden = (id: string) => {
        // Recarga la pagina con la OC seleccionada para traer sus partidas del backend.
        router.get('/admin/costos/facturas/create', { orden_compra_id: id }, { preserveState: false });
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Filtra partidas con cantidad vacia o 0 (opcional facturar solo algunas).
        const filled = data.detalles.filter((d) => (parseFloat(d.cantidad) || 0) > 0);

        post('/admin/costos/facturas', {
            data: {
                ...data,
                detalles: filled,
            } as unknown,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Factura" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Registrar factura</h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4">
                            <h2 className="text-lg font-medium border-b border-base-300 pb-2">Orden de compra</h2>

                            <FormField label="OC" htmlFor="orden_compra_id" error={errors.orden_compra_id} required>
                                <select
                                    id="orden_compra_id"
                                    className="select select-bordered w-full"
                                    value={data.orden_compra_id}
                                    onChange={(e) => switchOrden(e.target.value)}
                                >
                                    <option value="">Seleccionar OC</option>
                                    {ordenes.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.folio} — {o.proveedor?.razon_social ?? ''} — ${formatMoney(Number(o.total))}
                                        </option>
                                    ))}
                                </select>
                            </FormField>

                            {ordenCompra && (
                                <div className="text-sm text-base-content/70">
                                    Proveedor: <span className="font-medium">{ordenCompra.proveedor?.razon_social}</span>
                                </div>
                            )}
                        </div>

                        {ordenCompra && (
                            <>
                                <div className="space-y-4">
                                    <h2 className="text-lg font-medium border-b border-base-300 pb-2">Datos fiscales</h2>

                                    <div className="grid grid-cols-2 gap-4">
                                        <FormField label="UUID fiscal (CFDI)" htmlFor="uuid_fiscal" error={errors.uuid_fiscal}>
                                            <Input
                                                id="uuid_fiscal"
                                                value={data.uuid_fiscal}
                                                onChange={(e) => setData('uuid_fiscal', e.target.value)}
                                                placeholder="Timbre SAT"
                                            />
                                        </FormField>
                                        <FormField label="Folio fiscal" htmlFor="folio_fiscal" error={errors.folio_fiscal}>
                                            <Input
                                                id="folio_fiscal"
                                                value={data.folio_fiscal}
                                                onChange={(e) => setData('folio_fiscal', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="Fecha factura" htmlFor="fecha_factura" error={errors.fecha_factura}>
                                            <Input
                                                id="fecha_factura"
                                                type="date"
                                                value={data.fecha_factura}
                                                onChange={(e) => setData('fecha_factura', e.target.value)}
                                            />
                                        </FormField>
                                        <FormField label="Moneda" htmlFor="moneda" error={errors.moneda} required>
                                            <select
                                                id="moneda"
                                                className="select select-bordered w-full"
                                                value={data.moneda}
                                                onChange={(e) => setData('moneda', e.target.value)}
                                            >
                                                <option value="mxn">MXN</option>
                                                <option value="usd">USD</option>
                                                <option value="eur">EUR</option>
                                            </select>
                                        </FormField>
                                    </div>

                                    <FormField label="Notas" htmlFor="notas" error={errors.notas}>
                                        <textarea
                                            id="notas"
                                            className="textarea textarea-bordered w-full"
                                            value={data.notas}
                                            onChange={(e) => setData('notas', e.target.value)}
                                            rows={2}
                                        />
                                    </FormField>
                                </div>

                                <div className="space-y-4">
                                    <h2 className="text-lg font-medium border-b border-base-300 pb-2">Partidas facturadas</h2>
                                    <p className="text-sm text-base-content/60">
                                        Indica la cantidad que cubre esta factura por partida. El precio unitario se hereda
                                        de la OC pero puede ajustarse; el sistema valida que no excedas el saldo facturable.
                                    </p>

                                    <div className="overflow-x-auto">
                                        <table className="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Partida</th>
                                                    <th className="text-right">Ordenado</th>
                                                    <th className="text-right">Facturable</th>
                                                    <th className="w-28">Cantidad</th>
                                                    <th className="w-32">P. unitario</th>
                                                    <th className="text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {partidas.map((p, idx) => {
                                                    const saldo = saldosFacturables[p.id] ?? 0;
                                                    const cantidad = parseFloat(data.detalles[idx]?.cantidad) || 0;
                                                    const precio = parseFloat(data.detalles[idx]?.precio_unitario) || 0;
                                                    const subtotal = cantidad * precio;
                                                    const cantErr = errors[`detalles.${idx}.cantidad` as keyof typeof errors];

                                                    return (
                                                        <tr key={p.id}>
                                                            <td>
                                                                <div className="font-medium">{p.descripcion}</div>
                                                                <div className="text-xs text-base-content/60">
                                                                    {p.obra_rubro?.rubro?.codigo} · {p.unidad}
                                                                </div>
                                                            </td>
                                                            <td className="text-right">{Number(p.cantidad).toLocaleString('es-MX')}</td>
                                                            <td className={`text-right ${saldo <= 0 ? 'text-success' : 'text-warning'}`}>
                                                                {saldo.toLocaleString('es-MX')}
                                                            </td>
                                                            <td>
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    max={saldo}
                                                                    disabled={saldo <= 0}
                                                                    value={data.detalles[idx]?.cantidad ?? ''}
                                                                    onChange={(e) => updateDetalle(idx, 'cantidad', e.target.value)}
                                                                />
                                                                {cantErr && <div className="text-xs text-error mt-1">{cantErr}</div>}
                                                            </td>
                                                            <td>
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value={data.detalles[idx]?.precio_unitario ?? ''}
                                                                    onChange={(e) => updateDetalle(idx, 'precio_unitario', e.target.value)}
                                                                />
                                                            </td>
                                                            <td className="text-right">${formatMoney(subtotal)}</td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                    {errors.detalles && <p className="text-sm text-error">{errors.detalles}</p>}

                                    <div className="grid grid-cols-3 gap-4 border-t border-base-300 pt-3">
                                        <FormField label="IVA" htmlFor="iva" error={errors.iva}>
                                            <Input
                                                id="iva"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={data.iva}
                                                onChange={(e) => setData('iva', e.target.value)}
                                            />
                                        </FormField>
                                        <div>
                                            <div className="text-sm text-base-content/60">Subtotal</div>
                                            <div className="text-lg">${formatMoney(subtotalCalculado)}</div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-base-content/60">Total</div>
                                            <div className="text-2xl font-semibold">${formatMoney(totalCalculado)}</div>
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/facturas">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing || !ordenCompra || subtotalCalculado <= 0}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar factura
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
