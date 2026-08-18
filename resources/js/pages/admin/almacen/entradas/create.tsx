import { CapturadorPartidas, PARTIDA_VACIA, partidasSinVerificar } from '@/components/alm/capturador-partidas';
import { FechasMovimiento } from '@/components/alm/fechas-movimiento';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ALMACENES_DEMO,
    avanceOrden,
    ESTATUS_RECEPCION,
    facturasDeOrden,
    ordenesAbiertasDe,
    pendientePorRecibir,
    PRODUCTOS_DEMO,
    PROVEEDORES_DEMO,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmOcPartidaDemo, AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { PackageCheckIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
    { title: 'Nueva', href: '/admin/almacen/entradas/create' },
];

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/** Un renglón listo para cotejar: lo que la orden dejó pendiente y lo que ampara la factura. */
type RenglonPorRecibir = {
    partida: AlmOcPartidaDemo;
    /** Lo que la factura ampara de ese renglón; `null` si se recibe sin factura. */
    facturado: number | null;
    porRecibir: number;
};

/** Sólo un valor de la lista de la factura, sin inventar el que no está. */
const SIN_FACTURA = 'sin-factura';

/**
 * Alta de entrada por recepción.
 *
 * El almacenista no teclea lo que llegó desde cero: baja por proveedor → orden
 * de compra → factura, y coteja contra lo que alguien ya pidió. Ese orden no es
 * cosmético —es el que hace que la recepción sirva de algo—:
 *
 * - Contra la **orden** se compara lo pedido con lo que bajó del camión, así no
 *   entra material que nadie compró ni más de lo comprado.
 * - Contra la **factura** se amarra el papel con el fierro: la factura no se
 *   paga hasta que el almacén confirma que lo facturado llegó.
 *
 * El material puede llegar antes que el CFDI, así que también se puede recibir
 * sin factura; y queda la captura libre para lo que entra sin orden.
 */
export default function EntradaCreate() {
    const [almacen, setAlmacen] = useState('');
    const [fecha, setFecha] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [origen, setOrigen] = useState<'orden' | 'libre'>('orden');

    // Camino contra orden de compra.
    const [proveedorId, setProveedorId] = useState('');
    const [ordenId, setOrdenId] = useState('');
    const [facturaId, setFacturaId] = useState('');
    const [recibido, setRecibido] = useState<Record<number, string>>({});
    const [verificado, setVerificado] = useState<Record<number, boolean>>({});
    const [cierraFactura, setCierraFactura] = useState(true);

    // Camino libre, para lo que entra sin orden de compra.
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const ordenes = proveedorId ? ordenesAbiertasDe(Number(proveedorId)) : [];
    const orden = ordenes.find((o) => String(o.id) === ordenId) ?? null;
    const facturas = orden ? facturasDeOrden(orden.id) : [];
    const factura = facturas.find((f) => String(f.id) === facturaId) ?? null;

    /** Cambiar de proveedor u orden invalida lo elegido abajo: se limpia en cascada. */
    const elegirProveedor = (valor: string) => {
        setProveedorId(valor);
        setOrdenId('');
        setFacturaId('');
        setRecibido({});
        setVerificado({});
    };

    const elegirOrden = (valor: string) => {
        setOrdenId(valor);
        setFacturaId('');
        setRecibido({});
        setVerificado({});
    };

    const elegirFactura = (valor: string) => {
        setFacturaId(valor);
        setRecibido({});
        setVerificado({});
    };

    const renglones: RenglonPorRecibir[] =
        !orden || facturaId === ''
            ? []
            : orden.partidas.flatMap((partida) => {
                  const facturado =
                      factura?.renglones.find((r) => r.producto_id === partida.producto_id)?.cantidad_facturada ??
                      null;

                  // La factura ampara sólo parte de la orden: lo que no viene en
                  // ella no se recibe aquí, sino con la factura que lo traiga.
                  if (factura && facturado === null) {
                      return [];
                  }

                  const porRecibir = pendientePorRecibir(partida, factura);

                  return porRecibir > 0 ? [{ partida, facturado, porRecibir }] : [];
              });

    const cantidadDe = (productoId: number) => Number(recibido[productoId] || 0);
    const pideVerificacion = (productoId: number) =>
        PRODUCTOS_DEMO.find((p) => p.id === productoId)?.requiere_verificacion === true;

    /** Recibir de más es el error caro: entra al kardex material que nadie compró. */
    const excedidos = renglones.filter((r) => cantidadDe(r.partida.producto_id) > r.porRecibir);

    /** Lo que pide inspección y llegó, pero nadie ha revisado. */
    const sinRevisar = renglones.filter(
        (r) =>
            pideVerificacion(r.partida.producto_id) &&
            cantidadDe(r.partida.producto_id) > 0 &&
            !verificado[r.partida.producto_id],
    );

    const importe = renglones.reduce(
        (suma, r) => suma + cantidadDe(r.partida.producto_id) * r.partida.costo_unitario,
        0,
    );
    const conCantidad = renglones.filter((r) => cantidadDe(r.partida.producto_id) > 0).length;

    /** Lo que llegó completo: es lo que pasa el 90% de las veces. */
    const recibirTodo = () =>
        setRecibido(Object.fromEntries(renglones.map((r) => [r.partida.producto_id, String(r.porRecibir)])));

    const sinVerificarLibre = partidasSinVerificar(partidas);
    const contraOrden = origen === 'orden';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva entrada" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva entrada</h1>
                    <p className="text-base-content/60 mt-1 max-w-3xl text-sm">
                        Lo que capture aquí sube la existencia del almacén. Se recibe cotejando: primero el proveedor,
                        luego su orden de compra y luego la factura que trae el material. Lo que pide verificación no se
                        recepciona sin revisar en qué estado llegó.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacen}
                                    onValueChange={setAlmacen}
                                    placeholder="¿A dónde entra?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FechasMovimiento fecha={fecha} onChange={setFecha} label="Fecha de recepción" />
                        </div>

                        {/* La compra es el caso normal; lo demás entra por excepción y
                            conviene que se note cuál de los dos se está usando. */}
                        <div className="mt-4 flex flex-wrap gap-4">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="radio"
                                    name="origen"
                                    className="radio radio-sm"
                                    checked={contraOrden}
                                    onChange={() => setOrigen('orden')}
                                />
                                <span className="text-sm font-medium">Contra orden de compra</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="radio"
                                    name="origen"
                                    className="radio radio-sm"
                                    checked={!contraOrden}
                                    onChange={() => setOrigen('libre')}
                                />
                                <span className="text-sm font-medium">Sin orden de compra</span>
                                <span className="text-base-content/50 text-xs">
                                    (compra menor, donación, material sin OC)
                                </span>
                            </label>
                        </div>

                        {/* Contra qué se recibe va antes de observaciones: primero se
                            decide el documento y hasta el final se comenta cómo llegó.
                            Los tres en una línea porque son un solo gesto —proveedor →
                            orden → factura—, y cada uno se apaga hasta que el de su
                            izquierda decide. */}
                        {contraOrden ? (
                            <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField
                                    label="Proveedor"
                                    htmlFor="proveedor"
                                    required
                                    description="Quién entrega el material."
                                >
                                    <Select
                                        id="proveedor"
                                        value={proveedorId}
                                        onValueChange={elegirProveedor}
                                        placeholder="¿Quién trae el material?"
                                    >
                                        {PROVEEDORES_DEMO.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.nombre} — {p.rfc}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField
                                    label="Orden de compra"
                                    htmlFor="orden"
                                    required
                                    description={
                                        orden
                                            ? `${orden.destino} · faltan ${numero(avanceOrden(orden).pendiente)} de ${numero(avanceOrden(orden).pedido)}`
                                            : 'Sólo se puede recibir lo que se pidió, y hasta donde se pidió.'
                                    }
                                >
                                    <Select
                                        id="orden"
                                        value={ordenId}
                                        onValueChange={elegirOrden}
                                        disabled={!proveedorId}
                                        placeholder={
                                            !proveedorId
                                                ? 'Elige un proveedor'
                                                : ordenes.length === 0
                                                  ? 'Sin órdenes pendientes'
                                                  : '¿Contra cuál se recibe?'
                                        }
                                    >
                                        {ordenes.map((oc) => (
                                            <SelectItem key={oc.id} value={String(oc.id)}>
                                                {oc.folio} · {ESTATUS_RECEPCION[oc.estatus].etiqueta} · faltan{' '}
                                                {numero(avanceOrden(oc).pendiente)}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField
                                    label="Factura"
                                    htmlFor="factura"
                                    required
                                    description={
                                        factura
                                            ? `${moneda(factura.importe)} · ${factura.uuid}`
                                            : 'Recibir contra ella es lo que la destraba para pago.'
                                    }
                                >
                                    <Select
                                        id="factura"
                                        value={facturaId}
                                        onValueChange={elegirFactura}
                                        disabled={!orden}
                                        placeholder={orden ? '¿Con qué factura viene?' : 'Elige una orden'}
                                    >
                                        {facturas.map((f) => (
                                            // Las ya cotejadas se listan apagadas: que se
                                            // vean explica por qué la orden va a la mitad.
                                            <SelectItem
                                                key={f.id}
                                                value={String(f.id)}
                                                disabled={f.estatus === 'recibida'}
                                            >
                                                {f.folio} · {f.fecha} · {moneda(f.importe)}
                                                {f.estatus === 'recibida' ? ' (ya cotejada)' : ''}
                                            </SelectItem>
                                        ))}
                                        {/* El fierro suele ganarle al papel: no se deja el
                                            camión afuera esperando el CFDI. */}
                                        <SelectItem value={SIN_FACTURA}>
                                            Sin factura — el material llegó antes
                                        </SelectItem>
                                    </Select>
                                </FormField>
                            </div>
                        ) : (
                            <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField
                                    label="Proveedor"
                                    htmlFor="proveedor"
                                    description="Opcional: no todo lo que entra viene de una compra."
                                >
                                    <Select
                                        id="proveedor"
                                        value={proveedorId}
                                        onValueChange={elegirProveedor}
                                        placeholder="Sin proveedor"
                                    >
                                        {PROVEEDORES_DEMO.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.nombre} — {p.rfc}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            </div>
                        )}

                        <FormField label="Observaciones" htmlFor="observaciones" className="mt-4">
                            <textarea
                                id="observaciones"
                                className="textarea textarea-bordered w-full"
                                rows={2}
                                value={observaciones}
                                onChange={(e) => setObservaciones(e.target.value)}
                                placeholder="Remisión, quién entregó, cómo venía la carga..."
                            />
                        </FormField>
                    </div>

                    {contraOrden ? (
                        <>
                            {facturaId !== '' && orden && (
                                <div className="rounded-box border-base-300 border p-4">
                                    <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h2 className="text-lg font-semibold">Qué llegó</h2>
                                            <p className="text-base-content/60 text-sm">
                                                Se captura lo que de verdad bajó del camión. Lo que falte se queda
                                                pendiente en la orden para el siguiente viaje.
                                            </p>
                                        </div>
                                        {renglones.length > 0 && (
                                            <Button type="button" variant="outline" onClick={recibirTodo}>
                                                <PackageCheckIcon className="size-4" />
                                                Llegó completo
                                            </Button>
                                        )}
                                    </div>

                                    {renglones.length === 0 ? (
                                        <p className="text-base-content/50 py-4 text-center text-sm">
                                            No queda nada pendiente por recibir de esa selección.
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="table table-sm">
                                                <thead className="bg-base-200">
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Descripción</th>
                                                        <th className="text-right">Pedido</th>
                                                        <th className="text-right">Ya recibido</th>
                                                        {factura && <th className="text-right">Facturado</th>}
                                                        <th className="text-right">Por recibir</th>
                                                        <th className="w-32 text-right">Llegó</th>
                                                        <th className="text-right">Costo</th>
                                                        <th className="text-right">Importe</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {renglones.map(({ partida, facturado, porRecibir }) => {
                                                        const cantidad = cantidadDe(partida.producto_id);
                                                        const excede = cantidad > porRecibir;

                                                        return (
                                                            <tr key={partida.producto_id} className="hover">
                                                                <td className="font-mono text-xs">{partida.codigo}</td>
                                                                <td>
                                                                    {partida.descripcion}
                                                                    {pideVerificacion(partida.producto_id) && (
                                                                        <label className="mt-1 flex cursor-pointer items-center gap-2">
                                                                            <input
                                                                                type="checkbox"
                                                                                className="checkbox checkbox-xs"
                                                                                checked={
                                                                                    verificado[partida.producto_id] ??
                                                                                    false
                                                                                }
                                                                                onChange={(e) =>
                                                                                    setVerificado({
                                                                                        ...verificado,
                                                                                        [partida.producto_id]:
                                                                                            e.target.checked,
                                                                                    })
                                                                                }
                                                                            />
                                                                            <span className="text-base-content/60 text-xs">
                                                                                Revisada al recibir
                                                                            </span>
                                                                        </label>
                                                                    )}
                                                                </td>
                                                                <td className="text-right font-mono">
                                                                    {numero(partida.cantidad_pedida)}
                                                                </td>
                                                                <td className="text-base-content/60 text-right font-mono">
                                                                    {numero(partida.cantidad_recibida)}
                                                                </td>
                                                                {factura && (
                                                                    <td className="text-right font-mono">
                                                                        {facturado === null
                                                                            ? '—'
                                                                            : numero(facturado)}
                                                                    </td>
                                                                )}
                                                                <td className="text-right font-mono font-medium">
                                                                    {numero(porRecibir)}
                                                                    <span className="text-base-content/40">
                                                                        {' '}
                                                                        {partida.unidad}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <Input
                                                                        type="number"
                                                                        className="input-sm text-right"
                                                                        min={0}
                                                                        max={porRecibir}
                                                                        step="any"
                                                                        value={recibido[partida.producto_id] ?? ''}
                                                                        onChange={(e) =>
                                                                            setRecibido({
                                                                                ...recibido,
                                                                                [partida.producto_id]: e.target.value,
                                                                            })
                                                                        }
                                                                        error={excede}
                                                                        placeholder="0"
                                                                    />
                                                                </td>
                                                                <td className="text-right font-mono">
                                                                    {moneda(partida.costo_unitario)}
                                                                </td>
                                                                <td className="text-right font-mono">
                                                                    {moneda(cantidad * partida.costo_unitario)}
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    {factura && renglones.length > 0 && (
                                        <label className="mt-4 flex cursor-pointer items-start gap-2">
                                            <input
                                                type="checkbox"
                                                className="checkbox checkbox-sm mt-0.5"
                                                checked={cierraFactura}
                                                onChange={(e) => setCierraFactura(e.target.checked)}
                                            />
                                            <span className="text-sm">
                                                Con esto se completa la factura
                                                <span className="text-base-content/60">
                                                    {' '}
                                                    — se marca como recibida y pasa a revisión para pago. Desmárcalo si
                                                    el proveedor todavía debe material de esta factura.
                                                </span>
                                            </span>
                                        </label>
                                    )}

                                    {renglones.length > 0 && (
                                        <div className="border-base-300 mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm">
                                            <span className="text-base-content/60">
                                                {conCantidad} de {renglones.length} renglón(es) con cantidad
                                            </span>
                                            <span className="font-mono">Importe recibido: {moneda(importe)}</span>
                                        </div>
                                    )}
                                </div>
                            )}

                            {excedidos.length > 0 && (
                                <div className="alert alert-error">
                                    <TriangleAlertIcon className="size-5" />
                                    <span>
                                        No se puede recibir más de lo pendiente:{' '}
                                        {excedidos.map((r) => r.partida.codigo).join(', ')}. Si de verdad llegó de más,
                                        se corrige la orden en Costos, no aquí.
                                    </span>
                                </div>
                            )}

                            {sinRevisar.length > 0 && (
                                <div className="alert alert-error">
                                    <TriangleAlertIcon className="size-5" />
                                    <span>
                                        No se puede recepcionar: {sinRevisar.length}{' '}
                                        {sinRevisar.length === 1 ? 'equipo' : 'equipos'} sin revisar al recibir (
                                        {sinRevisar.map((r) => r.partida.codigo).join(', ')}).
                                    </span>
                                </div>
                            )}
                        </>
                    ) : (
                        <div>
                            <h2 className="mb-1 text-lg font-semibold">Partidas</h2>
                            <p className="text-base-content/60 mb-3 text-sm">
                                Sin orden que cotejar, la responsabilidad de lo que entra es de quien lo captura.
                                Conviene dejar en observaciones de dónde salió.
                            </p>
                            <CapturadorPartidas
                                partidas={partidas}
                                onChange={setPartidas}
                                conCosto
                                pedirVerificacionMantenimiento
                            />

                            {sinVerificarLibre.length > 0 && (
                                <div className="alert alert-error mt-4">
                                    <TriangleAlertIcon className="size-5" />
                                    <span>
                                        No se puede recepcionar: {sinVerificarLibre.length}{' '}
                                        {sinVerificarLibre.length === 1 ? 'equipo' : 'equipos'} sin verificación de
                                        mantenimiento.
                                    </span>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/entradas">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled title="La maqueta todavía no guarda">
                            Guardar entrada
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
