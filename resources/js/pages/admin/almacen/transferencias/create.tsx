import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmPartidaBorrador, AlmProductoOpcion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { InfoIcon, TruckIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
    { title: 'Envío', href: '/admin/almacen/transferencias/create' },
];

/** Tope de la fecha de transaccion: el material sale hoy o ya salio. */
const HOY = new Date().toISOString().slice(0, 10);

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type PedidoTransferible = {
    id: number;
    folio: string | null;
    /** El almacén al que se le pidió: de ahí sale el envío. */
    almacen_id: number;
    almacen: string | null;
    /** El almacén de obra al que va. `null` en los pedidos levantados antes de guardarlo. */
    almacen_destino_id: number | null;
    obra_id: number | null;
    obra: string | null;
    fecha_requerida: string | null;
    detalles: {
        id: number;
        articulo_id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        pendiente: number;
    }[];
};

type Renglon = AlmPartidaBorrador & { pedido_detalle_id: string };

const RENGLON_VACIO: Renglon = { ...PARTIDA_VACIA, pedido_detalle_id: '' };

/** Lo que le falta al pedido, ya como renglones del envío. */
const renglonesDe = (pedido: PedidoTransferible): Renglon[] =>
    pedido.detalles.map((d) => ({
        ...RENGLON_VACIO,
        articulo_id: String(d.articulo_id),
        pedido_detalle_id: String(d.id),
        cantidad: String(d.pendiente),
    }));

type Props = {
    almacenes: AlmAlmacenOpcion[];
    productos: AlmProductoOpcion[];
    pedidosTransferibles: PedidoTransferible[];
    pedidoSeleccionado: number | null;
};

/**
 * Lo que el almacén elegido guarda. Es a la vez la lista con la que se captura
 * y el saldo contra el que se avisa de faltantes: ofrecer el catálogo entero
 * sería ofrecer material que en esta bodega no hay.
 */
type Saldo = {
    id: number;
    articulo_id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    requiere_verificacion: boolean;
    cantidad: number;
};

/**
 * El primer tiempo de la transferencia: el envío.
 *
 * La recepción es otra pantalla y de otra persona — el `show` del documento.
 */
export default function TransferenciaCreate({ almacenes, pedidosTransferibles, pedidoSeleccionado }: Props) {
    // Si se llega desde el pedido ("Surtir con transferencia"), ya viene todo
    // resuelto: de dónde sale, a dónde va y qué le falta.
    const inicial = pedidosTransferibles.find((p) => p.id === pedidoSeleccionado);

    const form = useForm({
        almacen_origen_id: inicial ? String(inicial.almacen_id) : '',
        almacen_destino_id: inicial?.almacen_destino_id ? String(inicial.almacen_destino_id) : '',
        pedido_id: inicial ? String(inicial.id) : '',
        fecha_envio: HOY,
        observaciones: '',
        detalles: inicial ? renglonesDe(inicial) : [{ ...RENGLON_VACIO }],
    });

    const [saldos, setSaldos] = useState<Saldo[]>([]);

    useEffect(() => {
        if (form.data.almacen_origen_id === '') {
            setSaldos([]);

            return;
        }

        let vigente = true;

        fetch(`/admin/almacen/almacenes/${form.data.almacen_origen_id}/existencias`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((datos: Saldo[]) => vigente && setSaldos(datos))
            .catch(() => setSaldos([]));

        return () => {
            vigente = false;
        };
    }, [form.data.almacen_origen_id]);

    const pedido = pedidosTransferibles.find((p) => String(p.id) === form.data.pedido_id);

    // El destino no puede ser el origen: mover material a sí mismo no es nada.
    // Con pedido, además, sólo vale a donde va el pedido: su almacén o, en los
    // viejos que no lo guardaron, cualquiera de su obra.
    const destinos = almacenes.filter((a) => {
        if (String(a.id) === form.data.almacen_origen_id) {
            return false;
        }

        if (pedido === undefined) {
            return true;
        }

        return pedido.almacen_destino_id !== null ? a.id === pedido.almacen_destino_id : a.obra_id === pedido.obra_id;
    });

    const cargarPedido = (id: string) => {
        const elegido = pedidosTransferibles.find((p) => String(p.id) === id);

        if (elegido === undefined) {
            form.setData({ ...form.data, pedido_id: '', detalles: [{ ...RENGLON_VACIO }] });

            return;
        }

        // El pedido dice de dónde sale —el almacén al que se le pidió— y a dónde
        // va —el almacén de su obra—: no queda nada que elegir a mano.
        form.setData({
            ...form.data,
            pedido_id: id,
            almacen_origen_id: String(elegido.almacen_id),
            almacen_destino_id: elegido.almacen_destino_id === null ? '' : String(elegido.almacen_destino_id),
            detalles: renglonesDe(elegido),
        });
    };

    const disponibleDe = (productoId: number) => saldos.find((s) => s.articulo_id === productoId)?.cantidad ?? 0;

    const errorDe = (indice: number): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`detalles.${indice}.cantidad_enviada`];

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                articulo_id: d.articulo_id,
                pedido_detalle_id: d.pedido_detalle_id || null,
                cantidad_enviada: d.cantidad,
                observaciones: d.observaciones || null,
            })),
        }));

        form.post('/admin/almacen/transferencias');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo envío" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo envío</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Esto es el primer tiempo: el material sale del origen. El destino confirma después qué bajó del
                        camión, en el mismo folio.
                    </p>
                </div>

                <form onSubmit={enviar} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                label="Surte el pedido"
                                htmlFor="pedido_id"
                                error={form.errors.pedido_id}
                                className="md:col-span-3"
                                description="Elige el pedido y se llenan solos el origen, el destino y lo que le falta. Sólo aparecen los de obra: los de planta salen con una salida."
                            >
                                <Select
                                    id="pedido_id"
                                    value={form.data.pedido_id}
                                    onValueChange={cargarPedido}
                                    placeholder="Sin pedido"
                                >
                                    <SelectItem value="">Sin pedido</SelectItem>
                                    {pedidosTransferibles.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.folio} — {p.obra} (se le pidió a {p.almacen})
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Almacén origen"
                                htmlFor="almacen_origen_id"
                                error={form.errors.almacen_origen_id}
                                description={pedido ? 'Lo fija el pedido: sale del almacén al que se le pidió.' : undefined}
                                required
                            >
                                <Select
                                    id="almacen_origen_id"
                                    value={form.data.almacen_origen_id}
                                    onValueChange={(v) =>
                                        form.setData({
                                            ...form.data,
                                            almacen_origen_id: v,
                                            almacen_destino_id:
                                                form.data.almacen_destino_id === v ? '' : form.data.almacen_destino_id,
                                        })
                                    }
                                    disabled={pedido !== undefined}
                                    placeholder="¿De dónde sale?"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Almacén destino"
                                htmlFor="almacen_destino_id"
                                error={form.errors.almacen_destino_id}
                                description={
                                    pedido?.almacen_destino_id ? 'Lo fija el pedido: va al almacén de su obra.' : undefined
                                }
                                required
                            >
                                <Select
                                    id="almacen_destino_id"
                                    value={form.data.almacen_destino_id}
                                    onValueChange={(v) => form.setData('almacen_destino_id', v)}
                                    disabled={form.data.almacen_origen_id === '' || !!pedido?.almacen_destino_id}
                                    placeholder="¿A dónde va?"
                                >
                                    {destinos.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Fecha de envío"
                                htmlFor="fecha_envio"
                                error={form.errors.fecha_envio}
                                required
                            >
                                <Input
                                    id="fecha_envio"
                                    type="date"
                                    max={HOY}
                                    value={form.data.fecha_envio}
                                    onChange={(e) => form.setData('fecha_envio', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                className="md:col-span-3"
                                description="En qué va, con quién, qué hay que cuidar en el camino."
                            >
                                <Input
                                    id="observaciones"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                    placeholder="Va en la Ranger con el material de MBP"
                                />
                            </FormField>
                        </div>
                    </div>

                    <div className="alert">
                        <TruckIcon className="size-4" />
                        <span>
                            Al guardar, el material sale del origen y queda <strong>en tránsito</strong>: todavía no es
                            existencia del destino. La suma de los saldos no va a cuadrar hasta que la obra confirme lo
                            que recibió — eso es lo normal.
                        </span>
                    </div>

                    {pedido && (
                        <div className="alert alert-info">
                            <InfoIcon className="size-4" />
                            <span>
                                Se cargó lo que le falta a {pedido.folio} ({pedido.obra}): {pedido.detalles.length}{' '}
                                renglón(es), se necesitaba el {pedido.fecha_requerida}.
                            </span>
                        </div>
                    )}

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Qué se manda</h2>
                        {typeof form.errors.detalles === 'string' && (
                            <p className="text-error mb-2 text-sm">{form.errors.detalles}</p>
                        )}
                        {form.data.detalles.map((_, i) =>
                            errorDe(i) ? (
                                <p key={i} className="text-error mb-1 text-sm">
                                    Renglón {i + 1}: {errorDe(i)}
                                </p>
                            ) : null,
                        )}
                        {form.data.almacen_origen_id === '' && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el pedido o el almacén origen para ver cuánto hay de cada artículo.
                            </p>
                        )}
                        <CapturadorPartidas
                            partidas={form.data.detalles}
                            onChange={(detalles) =>
                                form.setData(
                                    'detalles',
                                    detalles.map((d, i) => ({
                                        ...RENGLON_VACIO,
                                        ...d,
                                        pedido_detalle_id: form.data.detalles[i]?.pedido_detalle_id ?? '',
                                    })),
                                )
                            }
                            articulos={saldos}
                            disponibleDe={form.data.almacen_origen_id === '' ? undefined : disponibleDe}
                        />
                    </div>

                    {pedido && (
                        <div className="rounded-box border-base-300 border p-4">
                            <h2 className="mb-2 font-medium">Lo que falta de {pedido.folio}</h2>
                            <ul className="text-base-content/70 space-y-1 text-sm">
                                {pedido.detalles.map((d) => (
                                    <li key={d.id}>
                                        <span className="font-mono text-xs">{d.codigo}</span> {d.descripcion} —{' '}
                                        <span className="font-mono">
                                            {numero(d.pendiente)} {d.unidad}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/transferencias">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Registrar envío
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
