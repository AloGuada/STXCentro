import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmPartidaBorrador, AlmProductoOpcion } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, InfoIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
    { title: 'Nueva', href: '/admin/almacen/entradas/create' },
];

type OrdenAbierta = { id: number; folio: string | null; proveedor: string | null; fecha: string | null };

type PartidaOrden = {
    id: number;
    descripcion: string | null;
    codigo: string | null;
    unidad: string | null;
    cantidad: number;
    recibido: number;
    pendiente: number;
    precio_unitario: number;
    mueve_kardex: boolean;
};

type OrdenParaRecibir = {
    id: number;
    folio: string | null;
    moneda: string;
    proveedor: string | null;
    partidas: PartidaOrden[];
    facturas: { id: number; folio: string | null; total: number }[];
};

type Props = {
    almacenes: AlmAlmacenOpcion[];
    proveedores: { id: number; nombre: string; rfc: string | null }[];
    productos: AlmProductoOpcion[];
    ordenesAbiertas: OrdenAbierta[];
    /** La orden que se está recibiendo, cuando la entrada cuelga de una. */
    orden: OrdenParaRecibir | null;
};

/** Lo que se captura por renglón de la orden: cuánto llegó y a qué precio. */
type RenglonOrden = { cantidad: string; precio: string; observaciones: string };

/** Tope de la fecha de transaccion: el material entro hoy o ya habia entrado. */
const HOY = new Date().toISOString().slice(0, 10);

const fmt = (n: number, moneda: string) =>
    `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${moneda === 'mxn' ? '' : ` ${moneda.toUpperCase()}`}`;

/**
 * La captura de entradas al almacén, en sus dos formas: contra una orden de
 * compra —el caso normal, material de proveedor— o sin orden. Es la única
 * puerta: aquí se dice a qué almacén entra el material, que es lo que mueve el
 * kardex.
 */
export default function EntradaCreate({ almacenes, productos, ordenesAbiertas, orden }: Props) {
    const [conOrden, setConOrden] = useState(orden !== null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva entrada" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva entrada</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que entra al almacén y sube el valor del inventario. Si viene de una orden de compra,
                        elígela: de ahí salen las partidas, el saldo por recibir y la factura que se destraba.
                    </p>
                </div>

                <div role="tablist" className="tabs-boxed tabs mb-6 w-fit">
                    <button
                        type="button"
                        role="tab"
                        className={`tab ${conOrden ? 'tab-active' : ''}`}
                        onClick={() => setConOrden(true)}
                    >
                        Con orden de compra
                    </button>
                    <button
                        type="button"
                        role="tab"
                        className={`tab ${conOrden ? '' : 'tab-active'}`}
                        onClick={() => {
                            setConOrden(false);
                            if (orden) router.get('/admin/almacen/entradas/create');
                        }}
                    >
                        Sin orden
                    </button>
                </div>

                {conOrden ? (
                    <EntradaConOrden almacenes={almacenes} ordenesAbiertas={ordenesAbiertas} orden={orden} />
                ) : (
                    <EntradaSinOrden almacenes={almacenes} productos={productos} />
                )}
            </div>
        </AppLayout>
    );
}

function EntradaConOrden({
    almacenes,
    ordenesAbiertas,
    orden,
}: {
    almacenes: AlmAlmacenOpcion[];
    ordenesAbiertas: OrdenAbierta[];
    orden: OrdenParaRecibir | null;
}) {
    const form = useForm({
        orden_compra_id: orden ? String(orden.id) : '',
        almacen_id: '',
        fecha_entrega: HOY,
        factura_id: '',
        completa_factura: false as boolean,
        observaciones: '',
        archivo: null as File | null,
        renglones: {} as Record<number, RenglonOrden>,
    });

    const partidas = orden?.partidas ?? [];
    const pendientes = partidas.filter((p) => p.pendiente > 0);

    const renglonDe = (id: number): RenglonOrden =>
        form.data.renglones[id] ?? { cantidad: '', precio: '', observaciones: '' };

    const cambiar = (id: number, campo: keyof RenglonOrden, valor: string) =>
        form.setData('renglones', { ...form.data.renglones, [id]: { ...renglonDe(id), [campo]: valor } });

    // La entrega es "completa" cuando no deja saldo en ninguna partida; si algo
    // queda pendiente es parcial. Se calcula, no se pregunta: el dato ya está
    // en lo capturado.
    const capturados = pendientes
        .map((p) => ({ partida: p, cantidad: Number(renglonDe(p.id).cantidad || 0) }))
        .filter((r) => r.cantidad > 0);

    const tipo = useMemo(() => {
        const cierraTodo = pendientes.every((p) => {
            const cantidad = Number(renglonDe(p.id).cantidad || 0);
            return Math.abs(cantidad - p.pendiente) < 0.0001;
        });
        return cierraTodo && capturados.length > 0 ? 'completa' : 'parcial';
    }, [form.data.renglones, pendientes, capturados.length]);

    const importe = capturados.reduce(
        (total, r) => total + r.cantidad * Number(renglonDe(r.partida.id).precio || r.partida.precio_unitario),
        0,
    );

    const errorDe = (campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[campo];

    const elegirOrden = (id: string) =>
        id
            ? router.get('/admin/almacen/entradas/create', { orden_compra_id: id })
            : router.get('/admin/almacen/entradas/create');

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            orden_compra_id: datos.orden_compra_id,
            almacen_id: datos.almacen_id,
            fecha_entrega: datos.fecha_entrega,
            tipo,
            factura_id: datos.factura_id || null,
            completa_factura: datos.completa_factura,
            observaciones: datos.observaciones || null,
            archivo: datos.archivo,
            detalles: capturados.map((r) => ({
                orden_compra_detalle_id: r.partida.id,
                cantidad_recibida: r.cantidad,
                precio_unitario: renglonDe(r.partida.id).precio || null,
                observaciones: renglonDe(r.partida.id).observaciones || null,
            })),
        }));

        form.post('/admin/almacen/entradas', { forceFormData: true });
    };

    if (ordenesAbiertas.length === 0 && !orden) {
        return (
            <div className="alert">
                <InfoIcon className="size-4" />
                <span>No hay órdenes de compra esperando material. Si el material llegó sin compra de por medio, captúralo como entrada sin orden.</span>
            </div>
        );
    }

    return (
        <form onSubmit={enviar} className="space-y-6">
            <div className="rounded-box border-base-300 border p-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField label="Orden de compra" htmlFor="orden_compra_id" error={errorDe('orden_compra_id')} required>
                        <Select
                            id="orden_compra_id"
                            value={form.data.orden_compra_id}
                            onValueChange={elegirOrden}
                            placeholder="¿De qué orden es el material?"
                        >
                            {ordenesAbiertas.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.folio} — {o.proveedor ?? 'sin proveedor'}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    {orden && (
                        <div className="text-sm">
                            <p className="text-base-content/60">Proveedor</p>
                            <p className="font-medium">{orden.proveedor ?? '—'}</p>
                            <Link
                                href={`/admin/costos/ordenes-compra/${orden.id}`}
                                className="link link-hover text-xs"
                            >
                                Ver la orden {orden.folio}
                            </Link>
                        </div>
                    )}
                </div>
            </div>

            {orden && (
                <>
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen_id" error={errorDe('almacen_id')} required>
                                <Select
                                    id="almacen_id"
                                    value={form.data.almacen_id}
                                    onValueChange={(v) => form.setData('almacen_id', v)}
                                    placeholder="¿A dónde entra?"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha" htmlFor="fecha_entrega" error={errorDe('fecha_entrega')} required>
                                <Input
                                    id="fecha_entrega"
                                    type="date"
                                    max={HOY}
                                    value={form.data.fecha_entrega}
                                    onChange={(e) => form.setData('fecha_entrega', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={errorDe('observaciones')}
                            >
                                <Input
                                    id="observaciones"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                    placeholder="Llegó incompleto, faltan 2 bultos"
                                />
                            </FormField>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-1 text-lg font-semibold">Qué llegó</h2>
                        <p className="text-base-content/60 mb-3 text-sm">
                            Captura solo lo que entró. El precio se hereda de la orden; escríbelo únicamente si el
                            proveedor surtió a otro, y el presupuesto se ajusta por la diferencia.
                        </p>

                        {errorDe('detalles') && <p className="text-error mb-2 text-sm">{errorDe('detalles')}</p>}

                        <div className="overflow-x-auto rounded-box border-base-300 border">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Partida</th>
                                        <th className="text-right">Pedido</th>
                                        <th className="text-right">Recibido</th>
                                        <th className="text-right">Pendiente</th>
                                        <th className="text-right">P. orden</th>
                                        <th className="text-right">Llegó</th>
                                        <th className="text-right">P. real</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {partidas.map((p, i) => {
                                        const renglon = renglonDe(p.id);
                                        const error =
                                            errorDe(`detalles.${i}.cantidad_recibida`) ??
                                            errorDe(`detalles.${i}.orden_compra_detalle_id`);

                                        return (
                                            <tr key={p.id} className={p.pendiente <= 0 ? 'opacity-50' : ''}>
                                                <td>
                                                    <div className="font-medium">{p.descripcion}</div>
                                                    <div className="text-base-content/50 flex items-center gap-2 text-xs">
                                                        {p.codigo && <span className="font-mono">{p.codigo}</span>}
                                                        {!p.mueve_kardex && (
                                                            <span className="badge badge-ghost badge-xs">
                                                                no mueve existencia
                                                            </span>
                                                        )}
                                                    </div>
                                                    {error && <p className="text-error text-xs">{error}</p>}
                                                </td>
                                                <td className="text-right font-mono text-xs">
                                                    {p.cantidad.toLocaleString('es-MX')} {p.unidad}
                                                </td>
                                                <td className="text-right font-mono text-xs">
                                                    {p.recibido.toLocaleString('es-MX')}
                                                </td>
                                                <td className="text-right font-mono text-xs font-semibold">
                                                    {p.pendiente.toLocaleString('es-MX')}
                                                </td>
                                                <td className="text-right font-mono text-xs">
                                                    {fmt(p.precio_unitario, orden.moneda)}
                                                </td>
                                                <td className="text-right">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max={p.pendiente}
                                                        className="input-xs w-24 text-right"
                                                        disabled={p.pendiente <= 0}
                                                        value={renglon.cantidad}
                                                        onChange={(e) => cambiar(p.id, 'cantidad', e.target.value)}
                                                    />
                                                </td>
                                                <td className="text-right">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        className="input-xs w-24 text-right"
                                                        disabled={p.pendiente <= 0}
                                                        placeholder="igual"
                                                        value={renglon.precio}
                                                        onChange={(e) => cambiar(p.id, 'precio', e.target.value)}
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colSpan={5} className="text-right font-semibold">
                                            Importe de esta entrada ({tipo})
                                        </td>
                                        <td colSpan={2} className="text-right font-semibold">
                                            {fmt(importe, orden.moneda)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-3 text-lg font-semibold">Factura</h2>

                        {orden.facturas.length === 0 ? (
                            <p className="text-base-content/60 text-sm">
                                Esta orden no tiene facturas esperando recepción. La entrada se registra igual; cuando
                                el proveedor facture, se liga en la siguiente recepción.
                            </p>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <FormField
                                    label="Factura que ampara el material"
                                    htmlFor="factura_id"
                                    error={errorDe('factura_id')}
                                >
                                    <Select
                                        id="factura_id"
                                        value={form.data.factura_id}
                                        onValueChange={(v) => form.setData('factura_id', v)}
                                        placeholder="Sin factura"
                                    >
                                        {orden.facturas.map((f) => (
                                            <SelectItem key={f.id} value={String(f.id)}>
                                                {f.folio} — {fmt(f.total, orden.moneda)}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>

                                <div className="flex flex-col justify-center gap-2">
                                    <label className="flex cursor-pointer items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm mt-0.5"
                                            disabled={!form.data.factura_id}
                                            checked={form.data.completa_factura}
                                            onChange={(e) => form.setData('completa_factura', e.target.checked)}
                                        />
                                        <span>
                                            Con esta entrada llegó <strong>todo</strong> lo que ampara la factura
                                            <span className="text-base-content/60 block text-xs">
                                                Es lo que la destraba: con el comprobante de recepción, pasa a
                                                aprobación.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        )}

                        <div className="mt-4">
                            <FormField
                                label="Evidencia (opcional)"
                                htmlFor="archivo"
                                error={errorDe('archivo')}
                                description="Remisión o foto de lo recibido."
                            >
                                <input
                                    id="archivo"
                                    type="file"
                                    className="file-input file-input-bordered file-input-sm w-full max-w-md"
                                    onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                        </div>
                    </div>

                    {capturados.length === 0 && (
                        <div className="alert alert-warning">
                            <AlertTriangleIcon className="size-4" />
                            <span>Captura al menos una cantidad recibida.</span>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/entradas">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || capturados.length === 0}>
                            Registrar entrada
                        </Button>
                    </div>
                </>
            )}
        </form>
    );
}

function EntradaSinOrden({
    almacenes,
    productos,
}: {
    almacenes: AlmAlmacenOpcion[];
    productos: AlmProductoOpcion[];
}) {
    const form = useForm({
        almacen_id: '',
        fecha_entrega: HOY,
        observaciones: '',
        detalles: [{ ...PARTIDA_VACIA }] as AlmPartidaBorrador[],
    });

    const errorDe = (indice: number, campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`detalles.${indice}.${campo}`];

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                producto_id: d.producto_id,
                cantidad_recibida: d.cantidad,
                precio_unitario: d.costo_unitario,
                observaciones: d.observaciones || null,
            })),
        }));

        form.post('/admin/almacen/entradas');
    };

    return (
        <form onSubmit={enviar} className="space-y-6">
            <div className="rounded-box border-base-300 border p-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                        <Select
                            id="almacen_id"
                            value={form.data.almacen_id}
                            onValueChange={(v) => form.setData('almacen_id', v)}
                            placeholder="¿A dónde entra?"
                        >
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label="Fecha" htmlFor="fecha_entrega" error={form.errors.fecha_entrega} required>
                        <Input
                            id="fecha_entrega"
                            type="date"
                            max={HOY}
                            value={form.data.fecha_entrega}
                            onChange={(e) => form.setData('fecha_entrega', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Observaciones"
                        htmlFor="observaciones"
                        error={form.errors.observaciones}
                        description="De dónde viene el material y por qué no hay orden."
                    >
                        <Input
                            id="observaciones"
                            value={form.data.observaciones}
                            onChange={(e) => form.setData('observaciones', e.target.value)}
                            placeholder="Lo trajo el proveedor de la obra vecina"
                        />
                    </FormField>
                </div>
            </div>

            <div>
                <h2 className="mb-3 text-lg font-semibold">Qué llegó</h2>
                <p className="text-base-content/60 mb-2 text-sm">
                    El costo es obligatorio: sin orden no hay de dónde heredarlo, y material que entra sin costo deja el
                    promedio del artículo mintiendo sobre lo que vale el inventario.
                </p>
                {typeof form.errors.detalles === 'string' && (
                    <p className="text-error mb-2 text-sm">{form.errors.detalles}</p>
                )}
                {form.data.detalles.map((_, i) => {
                    const error = errorDe(i, 'precio_unitario') ?? errorDe(i, 'producto_id');

                    return error ? (
                        <p key={i} className="text-error mb-1 text-sm">
                            Renglón {i + 1}: {error}
                        </p>
                    ) : null;
                })}
                <CapturadorPartidas
                    partidas={form.data.detalles}
                    onChange={(detalles) => form.setData('detalles', detalles)}
                    productos={productos}
                    conCosto
                    avisarFaltante={false}
                    pedirVerificacionMantenimiento
                />
            </div>

            <div className="flex justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href="/admin/almacen/entradas">Cancelar</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Registrar entrada
                </Button>
            </div>
        </form>
    );
}
