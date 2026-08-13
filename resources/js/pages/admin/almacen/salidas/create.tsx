import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, disponibleDemo, pedidosSurtibles } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Salidas', href: '/admin/almacen/salidas' },
    { title: 'Nueva', href: '/admin/almacen/salidas/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

export default function SalidaCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [pedidoId, setPedidoId] = useState('');
    const [obra, setObra] = useState('');
    const [recibe, setRecibe] = useState('');
    const [fecha, setFecha] = useState('');
    const [motivo, setMotivo] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;
    const surtibles = pedidosSurtibles(claveAlmacen);
    const pedido = surtibles.find((p) => String(p.id) === pedidoId);

    /** Cambiar de almacén invalida el pedido: ya no es del mismo pañol. */
    const elegirAlmacen = (valor: string) => {
        setAlmacenId(valor);
        setPedidoId('');
    };

    /**
     * Al surtir un pedido los renglones se traen solos, con la cantidad que
     * falta por entregar. El almacenista puede bajarla si surte de menos: el
     * resto queda pendiente para la siguiente salida.
     */
    const elegirPedido = (valor: string) => {
        setPedidoId(valor);

        const elegido = surtibles.find((p) => String(p.id) === valor);

        if (!elegido) {
            setPartidas([{ ...PARTIDA_VACIA }]);

            return;
        }

        setObra(String(OBRAS_DEMO.find((o) => o.etiqueta === elegido.obra)?.id ?? ''));
        setPartidas(
            elegido.detalle
                .filter((d) => d.cantidad_surtida < d.cantidad_solicitada)
                .map((d) => ({
                    ...PARTIDA_VACIA,
                    producto_id: String(d.producto_id),
                    cantidad: String(d.cantidad_solicitada - d.cantidad_surtida),
                })),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva salida" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva salida</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Elige primero el almacén: de ahí sale la existencia con la que se validan las cantidades.
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
                                    value={almacenId}
                                    onValueChange={elegirAlmacen}
                                    placeholder="¿De dónde sale?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Pedido"
                                htmlFor="pedido"
                                description={
                                    claveAlmacen
                                        ? 'Déjalo vacío si es una salida urgente, sin nadie que la haya pedido.'
                                        : 'Elige primero el almacén.'
                                }
                            >
                                <Select
                                    id="pedido"
                                    value={pedidoId}
                                    onValueChange={elegirPedido}
                                    placeholder="Sin pedido — salida directa"
                                    disabled={!claveAlmacen}
                                >
                                    {surtibles.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.folio} — {p.obra}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Obra destino"
                                htmlFor="obra"
                                description="Déjala vacía si es consumo del propio almacén."
                            >
                                <Select id="obra" value={obra} onValueChange={setObra} placeholder="Consumo interno">
                                    {OBRAS_DEMO.map((o) => (
                                        <SelectItem key={o.id} value={String(o.id)}>
                                            {o.etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha" htmlFor="fecha" required>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={fecha}
                                    onChange={(e) => setFecha(e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Recibe"
                                htmlFor="recibe"
                                description="Nombre de quien se lleva el material; es el que firma el vale impreso."
                                required
                            >
                                <Input
                                    id="recibe"
                                    value={recibe}
                                    onChange={(e) => setRecibe(e.target.value)}
                                    placeholder="Cuadrilla 3, A. Pérez..."
                                />
                            </FormField>

                            <FormField label="Motivo" htmlFor="motivo" className="md:col-span-2" required>
                                <Input
                                    id="motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder="Montaje eje 4, sellado de fachada..."
                                />
                            </FormField>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Partidas</h2>
                        {!claveAlmacen && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver la existencia disponible de cada producto.
                            </p>
                        )}
                        {pedido && (
                            <div className="alert alert-info mb-3">
                                <span>
                                    Surtiendo <strong>{pedido.folio}</strong>: se trajeron los renglones que faltan
                                    por entregar. Baja la cantidad si surtes de menos — lo que quede pendiente se
                                    puede sacar en otra salida contra el mismo pedido.
                                </span>
                            </div>
                        )}
                        <CapturadorPartidas
                            partidas={partidas}
                            onChange={setPartidas}
                            disponibleDe={
                                claveAlmacen ? (codigo) => disponibleDemo(claveAlmacen, codigo) : undefined
                            }
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/salidas">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar salida
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
