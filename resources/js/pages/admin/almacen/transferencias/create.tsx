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
import { InfoIcon, TruckIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
    { title: 'Envío', href: '/admin/almacen/transferencias/create' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type PedidoTransferible = {
    id: number;
    folio: string | null;
    obra_id: number | null;
    obra: string | null;
    fecha_requerida: string | null;
    detalles: {
        id: number;
        producto_id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        pendiente: number;
    }[];
};

type Renglon = AlmPartidaBorrador & { pedido_detalle_id: string };

const RENGLON_VACIO: Renglon = { ...PARTIDA_VACIA, pedido_detalle_id: '' };

type Props = {
    almacenes: AlmAlmacenOpcion[];
    productos: AlmProductoOpcion[];
    pedidosTransferibles: PedidoTransferible[];
    pedidoSeleccionado: number | null;
};

type Saldo = { producto_id: number; cantidad: number };

/**
 * El primer tiempo de la transferencia: el envío.
 *
 * La recepción es otra pantalla y de otra persona — el `show` del documento.
 */
export default function TransferenciaCreate({
    almacenes,
    productos,
    pedidosTransferibles,
    pedidoSeleccionado,
}: Props) {
    const form = useForm({
        almacen_origen_id: '',
        almacen_destino_id: '',
        pedido_id: pedidoSeleccionado === null ? '' : String(pedidoSeleccionado),
        fecha_envio: new Date().toISOString().slice(0, 10),
        observaciones: '',
        detalles: [{ ...RENGLON_VACIO }] as Renglon[],
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
    const destinos = almacenes.filter((a) => String(a.id) !== form.data.almacen_origen_id);

    const cargarPedido = (id: string) => {
        form.setData('pedido_id', id);

        const elegido = pedidosTransferibles.find((p) => String(p.id) === id);

        if (elegido === undefined) {
            form.setData('detalles', [{ ...RENGLON_VACIO }]);

            return;
        }

        form.setData(
            'detalles',
            elegido.detalles.map((d) => ({
                ...RENGLON_VACIO,
                producto_id: String(d.producto_id),
                pedido_detalle_id: String(d.id),
                cantidad: String(d.pendiente),
            })),
        );
    };

    const disponibleDe = (productoId: number) => saldos.find((s) => s.producto_id === productoId)?.cantidad ?? 0;

    const errorDe = (indice: number): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`detalles.${indice}.cantidad_enviada`];

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                producto_id: d.producto_id,
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
                                label="Almacén origen"
                                htmlFor="almacen_origen_id"
                                error={form.errors.almacen_origen_id}
                                required
                            >
                                <Select
                                    id="almacen_origen_id"
                                    value={form.data.almacen_origen_id}
                                    onValueChange={(v) => {
                                        form.setData('almacen_origen_id', v);
                                        form.setData('almacen_destino_id', '');
                                        router.get(
                                            '/admin/almacen/transferencias/create',
                                            { almacen_origen_id: v },
                                            { preserveState: true, replace: true, only: ['pedidosTransferibles'] },
                                        );
                                    }}
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
                                required
                            >
                                <Select
                                    id="almacen_destino_id"
                                    value={form.data.almacen_destino_id}
                                    onValueChange={(v) => form.setData('almacen_destino_id', v)}
                                    disabled={form.data.almacen_origen_id === ''}
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
                                label="Surte el pedido"
                                htmlFor="pedido_id"
                                error={form.errors.pedido_id}
                                description="Sólo aparecen los pedidos de obra: los de planta salen con una salida."
                            >
                                <Select
                                    id="pedido_id"
                                    value={form.data.pedido_id}
                                    onValueChange={cargarPedido}
                                    disabled={form.data.almacen_origen_id === ''}
                                    placeholder="Sin pedido"
                                >
                                    <SelectItem value="">Sin pedido</SelectItem>
                                    {pedidosTransferibles.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.folio} — {p.obra}
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
                                    value={form.data.fecha_envio}
                                    onChange={(e) => form.setData('fecha_envio', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                className="md:col-span-2"
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
                                Elige el almacén origen para ver cuánto hay de cada artículo.
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
                            productos={productos}
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
