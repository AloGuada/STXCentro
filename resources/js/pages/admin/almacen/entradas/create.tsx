import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
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
    /** Lo que la orden carga de impuestos sobre su subtotal: 1.16 en la normal. */
    factor_impuestos: number;
    partidas: PartidaOrden[];
    facturas: { id: number; folio: string | null; total: number }[];
};

type Props = {
    almacenes: AlmAlmacenOpcion[];
    proveedores: { id: number; nombre: string; rfc: string | null }[];
    /** Del catálogo de Almacén: el formulario captura `articulo_id`. */
    articulos: AlmProductoOpcion[];
    ordenesAbiertas: OrdenAbierta[];
    /** La orden que se está recibiendo, cuando la entrada cuelga de una. */
    orden: OrdenParaRecibir | null;
};

/** Lo que se captura por renglón de la orden: cuánto llegó y a qué precio. */
type RenglonOrden = { cantidad: string; precio: string; observaciones: string };

type OrigenFactura = 'nueva' | 'existente';

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
export default function EntradaCreate({ almacenes, articulos, ordenesAbiertas, orden }: Props) {
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
                    <EntradaSinOrden almacenes={almacenes} articulos={articulos} />
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
        origen_factura: 'nueva' as OrigenFactura,
        factura_id: '',
        completa_factura: false as boolean,
        observaciones: '',
        xml: null as File | null,
        pdf: null as File | null,
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

    // Anticipo con el factor de la orden entera: quien manda es el servidor, que
    // además pesa las partidas exentas y las retenciones del proveedor.
    const totalConImpuestos = Math.round(importe * (orden?.factor_impuestos ?? 1) * 100) / 100;
    const porcentajeImpuestos = `${Math.round(((orden?.factor_impuestos ?? 1) - 1) * 1000) / 10}%`;

    const sinFacturasPrevias = (orden?.facturas.length ?? 0) === 0;

    // Al cambiar de camino se limpia el otro: mandar los dos obligaría a
    // desempatar entre una factura y un CFDI que pueden no ser el mismo.
    const elegirOrigenFactura = (origen: OrigenFactura) => {
        form.setData((datos) => ({
            ...datos,
            origen_factura: origen,
            factura_id: origen === 'existente' ? datos.factura_id : '',
            xml: origen === 'nueva' ? datos.xml : null,
            pdf: origen === 'nueva' ? datos.pdf : null,
        }));
    };

    const facturaIncompleta =
        form.data.origen_factura === 'nueva'
            ? !form.data.xml || !form.data.pdf
            : !form.data.factura_id;

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
            factura_id: datos.origen_factura === 'existente' ? datos.factura_id || null : null,
            xml: datos.origen_factura === 'nueva' ? datos.xml : null,
            pdf: datos.origen_factura === 'nueva' ? datos.pdf : null,
            completa_factura: datos.completa_factura,
            observaciones: datos.observaciones || null,
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
                        <SearchSelect
                            options={ordenesAbiertas.map((o) => ({
                                value: String(o.id),
                                label: `${o.folio ?? 'sin folio'} — ${o.proveedor ?? 'sin proveedor'}`,
                            }))}
                            value={form.data.orden_compra_id}
                            onValueChange={elegirOrden}
                            placeholder="¿De qué orden es el material?"
                            maxOptions={8}
                        />
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
                                        <td colSpan={5} className="text-right">
                                            Importe de esta entrada ({tipo})
                                        </td>
                                        <td colSpan={2} className="text-right">
                                            {fmt(importe, orden.moneda)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={5} className="text-right">
                                            Impuestos ({porcentajeImpuestos})
                                        </td>
                                        <td colSpan={2} className="text-right">
                                            {fmt(totalConImpuestos - importe, orden.moneda)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={5} className="text-right font-semibold">
                                            Total que debe decir la factura
                                        </td>
                                        <td colSpan={2} className="text-right font-semibold">
                                            {fmt(totalConImpuestos, orden.moneda)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-3 text-lg font-semibold">Factura</h2>
                        <div className="mb-4 flex flex-wrap gap-6 text-sm">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="radio"
                                    className="radio radio-sm"
                                    name="origen_factura"
                                    checked={form.data.origen_factura === 'nueva'}
                                    onChange={() => elegirOrigenFactura('nueva')}
                                />
                                <span>Nueva factura</span>
                            </label>
                            <label
                                className={`flex items-center gap-2 ${sinFacturasPrevias ? 'opacity-50' : 'cursor-pointer'}`}
                            >
                                <input
                                    type="radio"
                                    className="radio radio-sm"
                                    name="origen_factura"
                                    disabled={sinFacturasPrevias}
                                    checked={form.data.origen_factura === 'existente'}
                                    onChange={() => elegirOrigenFactura('existente')}
                                />
                                <span>Factura existente{sinFacturasPrevias && ' (no hay ninguna)'}</span>
                            </label>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {form.data.origen_factura === 'nueva' ? (
                                <>
                                    <FormField label="XML del CFDI" htmlFor="xml" error={errorDe('xml')} required>
                                        <input
                                            id="xml"
                                            type="file"
                                            accept=".xml,application/xml,text/xml"
                                            className="file-input file-input-bordered file-input-sm w-full"
                                            onChange={(e) => form.setData('xml', e.target.files?.[0] ?? null)}
                                        />
                                    </FormField>

                                    <FormField label="PDF de la factura" htmlFor="pdf" error={errorDe('pdf')} required>
                                        <input
                                            id="pdf"
                                            type="file"
                                            accept="application/pdf"
                                            className="file-input file-input-bordered file-input-sm w-full"
                                            onChange={(e) => form.setData('pdf', e.target.files?.[0] ?? null)}
                                        />
                                    </FormField>
                                </>
                            ) : (
                                <FormField
                                    label="Factura que ampara el material"
                                    htmlFor="factura_id"
                                    error={errorDe('factura_id')}
                                    className="md:col-span-2"
                                    required
                                >
                                    <SearchSelect
                                        options={orden.facturas.map((f) => ({
                                            value: String(f.id),
                                            label: `${f.folio ?? 'sin folio'} — ${fmt(f.total, orden.moneda)}`,
                                        }))}
                                        value={form.data.factura_id}
                                        onValueChange={(v) => form.setData('factura_id', v)}
                                        placeholder="Busca el folio fiscal"
                                        maxOptions={8}
                                    />
                                </FormField>
                            )}

                            <div className="flex flex-col justify-center">
                                <label className="flex cursor-pointer items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={form.data.completa_factura}
                                        onChange={(e) => form.setData('completa_factura', e.target.checked)}
                                    />
                                    <span>
                                        Con esta entrada llegó <strong>todo</strong> lo que ampara la factura
                                        <span className="text-base-content/60 block text-xs">
                                            Es lo que la destraba: con el comprobante de recepción, pasa a aprobación.
                                        </span>
                                    </span>
                                </label>
                            </div>
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
                        <Button
                            type="submit"
                            disabled={form.processing || capturados.length === 0 || facturaIncompleta}
                        >
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
    articulos,
}: {
    almacenes: AlmAlmacenOpcion[];
    articulos: AlmProductoOpcion[];
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
                articulo_id: d.articulo_id,
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
                    const error = errorDe(i, 'precio_unitario') ?? errorDe(i, 'articulo_id');

                    return error ? (
                        <p key={i} className="text-error mb-1 text-sm">
                            Renglón {i + 1}: {error}
                        </p>
                    ) : null;
                })}
                <CapturadorPartidas
                    partidas={form.data.detalles}
                    onChange={(detalles) => form.setData('detalles', detalles)}
                    articulos={articulos}
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
