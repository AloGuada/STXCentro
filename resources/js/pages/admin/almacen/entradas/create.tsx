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
import { InfoIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
    { title: 'Sin orden', href: '/admin/almacen/entradas/create' },
];

type Props = {
    almacenes: AlmAlmacenOpcion[];
    proveedores: { id: number; nombre: string; rfc: string | null }[];
    productos: AlmProductoOpcion[];
    ordenesAbiertas: { id: number; folio: string | null; proveedor: string | null; fecha: string | null }[];
};

/**
 * La entrada sin orden de compra: material que llega sin compra de por medio.
 *
 * La recepción contra una orden se captura desde la orden, donde vive el tope
 * contra lo pedido y lo facturado y el ajuste de presupuesto por diferencia de
 * precio. Duplicar ese formulario aquí sería duplicar esas tres reglas.
 */
export default function EntradaCreate({ almacenes, productos, ordenesAbiertas }: Props) {
    const form = useForm({
        almacen_id: '',
        fecha_entrega: new Date().toISOString().slice(0, 10),
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entrada sin orden" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Entrada sin orden</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que llega sin compra de por medio. Suma al kardex igual que una recepción normal, pero
                        no cuelga de ninguna orden.
                    </p>
                </div>

                {ordenesAbiertas.length > 0 && (
                    <div className="alert alert-info mb-4">
                        <InfoIcon className="size-4" />
                        <span>
                            ¿Estás recibiendo material de una orden de compra? Captúralo desde la orden: ahí está lo que
                            se pidió, lo que se facturó y el ajuste de precio.
                        </span>
                        <Link href="/admin/costos/recepciones" className="btn btn-sm">
                            Ir a órdenes por recibir
                        </Link>
                    </div>
                )}

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

                            <FormField
                                label="Fecha"
                                htmlFor="fecha_entrega"
                                error={form.errors.fecha_entrega}
                                required
                            >
                                <Input
                                    id="fecha_entrega"
                                    type="date"
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
                            El costo es obligatorio: sin orden no hay de dónde heredarlo, y material que entra sin costo
                            deja el promedio del artículo mintiendo sobre lo que vale el inventario.
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
            </div>
        </AppLayout>
    );
}
