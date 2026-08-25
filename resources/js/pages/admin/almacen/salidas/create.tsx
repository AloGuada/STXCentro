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
import { InfoIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Salidas', href: '/admin/almacen/salidas' },
    { title: 'Nueva', href: '/admin/almacen/salidas/create' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/** El almacen captura lo que ya paso: el material sale hoy o ya salio. */
const HOY = new Date().toISOString().slice(0, 10);

type PedidoSurtible = {
    id: number;
    folio: string | null;
    departamento: string | null;
    recibe: string | null;
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

/** Un renglón de la salida: el capturador más el amarre al renglón del pedido. */
type Renglon = AlmPartidaBorrador & { pedido_detalle_id: string };

const RENGLON_VACIO: Renglon = { ...PARTIDA_VACIA, pedido_detalle_id: '' };

type Props = {
    almacenes: AlmAlmacenOpcion[];
    departamentos: { id: number; descripcion: string }[];
    obras: { id: number; no: string; descripcion: string }[];
    gruposTrabajo: { id: number; descripcion: string }[];
    productos: AlmProductoOpcion[];
    /** Sólo los de consumo interno: los de obra van por transferencia. */
    pedidosSurtibles: PedidoSurtible[];
    pedidoSeleccionado: number | null;
};

/** El saldo del almacén elegido, para avisar de faltantes antes de guardar. */
type Saldo = { producto_id: number; cantidad: number };

export default function SalidaCreate({
    almacenes,
    departamentos,
    obras,
    gruposTrabajo,
    productos,
    pedidosSurtibles,
    pedidoSeleccionado,
}: Props) {
    const form = useForm({
        almacen_id: '',
        pedido_id: pedidoSeleccionado === null ? '' : String(pedidoSeleccionado),
        obra_destino_id: '',
        departamento_id: '',
        grupo_trabajo_id: '',
        recibe_nombre: '',
        fecha: HOY,
        observaciones: '',
        detalles: [{ ...RENGLON_VACIO }] as Renglon[],
    });

    const [saldos, setSaldos] = useState<Saldo[]>([]);

    useEffect(() => {
        if (form.data.almacen_id === '') {
            setSaldos([]);

            return;
        }

        let vigente = true;

        fetch(`/admin/almacen/almacenes/${form.data.almacen_id}/existencias`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((datos: Saldo[]) => vigente && setSaldos(datos))
            .catch(() => setSaldos([]));

        return () => {
            vigente = false;
        };
    }, [form.data.almacen_id]);

    const pedido = pedidosSurtibles.find((p) => String(p.id) === form.data.pedido_id);
    const almacen = almacenes.find((a) => String(a.id) === form.data.almacen_id);

    /**
     * En planta el material no se va a otro domicilio: se consume aqui mismo,
     * asi que hay que decir a que area y con que modulo. Cuando surte un pedido
     * ya lo dijo el pedido, y en un almacen de obra el destino es la obra.
     */
    const consumoDePlanta = almacen !== undefined && almacen.obra_id === null && form.data.pedido_id === '';

    /**
     * Al elegir un pedido, la salida arranca con lo que le falta a cada renglón:
     * es el caso normal, y teclearlo otra vez es donde se equivoca la cantidad.
     */
    const cargarPedido = (id: string) => {
        form.setData('pedido_id', id);

        const elegido = pedidosSurtibles.find((p) => String(p.id) === id);

        if (elegido === undefined) {
            form.setData('detalles', [{ ...RENGLON_VACIO }]);

            return;
        }

        form.setData('recibe_nombre', elegido.recibe ?? '');
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

    // Al dejar de ser consumo de planta los dos campos desaparecen; limpiarlos
    // evita mandar en el POST algo que la pantalla ya no ensena.
    useEffect(() => {
        if (consumoDePlanta) {
            return;
        }

        if (form.data.departamento_id !== '') {
            form.setData('departamento_id', '');
        }

        if (form.data.grupo_trabajo_id !== '') {
            form.setData('grupo_trabajo_id', '');
        }
    }, [consumoDePlanta]);

    const disponibleDe = (productoId: number) => saldos.find((s) => s.producto_id === productoId)?.cantidad ?? 0;

    const errorDe = (indice: number): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`detalles.${indice}.cantidad`];

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                producto_id: d.producto_id,
                pedido_detalle_id: d.pedido_detalle_id || null,
                cantidad: d.cantidad,
                observaciones: d.observaciones || null,
            })),
        }));

        form.post('/admin/almacen/salidas');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva salida" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva salida</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que sale del almacén y se queda en el mismo domicilio. Aquí sí es error sacar de más:
                        este material sale de verdad.
                    </p>
                </div>

                <form onSubmit={enviar} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Fecha" htmlFor="fecha" error={form.errors.fecha} required>
                                <Input
                                    id="fecha"
                                    type="date"
                                    max={HOY}
                                    value={form.data.fecha}
                                    onChange={(e) => form.setData('fecha', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                                <Select
                                    id="almacen_id"
                                    value={form.data.almacen_id}
                                    onValueChange={(v) => {
                                        form.setData('almacen_id', v);
                                        // Los pedidos surtibles son de un almacén:
                                        // se vuelven a pedir al servidor.
                                        router.get(
                                            '/admin/almacen/salidas/create',
                                            { almacen_id: v },
                                            { preserveState: true, replace: true, only: ['pedidosSurtibles'] },
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
                                label="Surte el pedido"
                                htmlFor="pedido_id"
                                error={form.errors.pedido_id}
                                description="Opcional: la salida directa se conserva para lo urgente."
                            >
                                <Select
                                    id="pedido_id"
                                    value={form.data.pedido_id}
                                    onValueChange={cargarPedido}
                                    disabled={form.data.almacen_id === ''}
                                    placeholder="Salida directa, sin pedido"
                                >
                                    <SelectItem value="">Salida directa, sin pedido</SelectItem>
                                    {pedidosSurtibles.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.folio} — {p.departamento}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Quién recibe"
                                htmlFor="recibe_nombre"
                                error={form.errors.recibe_nombre}
                                description="Es quien firma el vale."
                                required
                            >
                                <Input
                                    id="recibe_nombre"
                                    value={form.data.recibe_nombre}
                                    onChange={(e) => form.setData('recibe_nombre', e.target.value)}
                                    placeholder="Cuadrilla 3, A. Pérez..."
                                />
                            </FormField>

                            {consumoDePlanta && (
                                <>
                                    <FormField
                                        label="Área"
                                        htmlFor="departamento_id"
                                        error={form.errors.departamento_id}
                                        description="A quién se le carga el consumo."
                                        required
                                    >
                                        <Select
                                            id="departamento_id"
                                            value={form.data.departamento_id}
                                            onValueChange={(v) => form.setData('departamento_id', v)}
                                            placeholder="¿A qué área se le carga?"
                                        >
                                            {departamentos.map((d) => (
                                                <SelectItem key={d.id} value={String(d.id)}>
                                                    {d.descripcion}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                    </FormField>

                                    <FormField
                                        label="Módulo"
                                        htmlFor="grupo_trabajo_id"
                                        error={form.errors.grupo_trabajo_id}
                                        description="El grupo de trabajo que se lleva el material."
                                    >
                                        <Select
                                            id="grupo_trabajo_id"
                                            value={form.data.grupo_trabajo_id}
                                            onValueChange={(v) => form.setData('grupo_trabajo_id', v)}
                                            placeholder="Sin módulo"
                                        >
                                            <SelectItem value="">Sin módulo</SelectItem>
                                            {gruposTrabajo.map((g) => (
                                                <SelectItem key={g.id} value={String(g.id)}>
                                                    {g.descripcion}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                    </FormField>
                                </>
                            )}

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                className="md:col-span-3"
                            >
                                <Input
                                    id="observaciones"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                />
                            </FormField>
                        </div>
                    </div>

                    {pedido && (
                        <div className="alert">
                            <InfoIcon className="size-4" />
                            <span>
                                Se cargó lo que le falta a {pedido.folio}: {pedido.detalles.length} renglón(es), se
                                necesitaba el {pedido.fecha_requerida}. Puedes entregar menos, pero no más de lo
                                pendiente.
                            </span>
                        </div>
                    )}

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Qué se entrega</h2>
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
                        {form.data.almacen_id === '' && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver cuánto hay de cada artículo.
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
                                        // El amarre al renglón del pedido sobrevive
                                        // a la edición del capturador, que no lo
                                        // conoce.
                                        pedido_detalle_id: form.data.detalles[i]?.pedido_detalle_id ?? '',
                                    })),
                                )
                            }
                            productos={productos}
                            disponibleDe={form.data.almacen_id === '' ? undefined : disponibleDe}
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
                            <Link href="/admin/almacen/salidas">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Guardar salida
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
