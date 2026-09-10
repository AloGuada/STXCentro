import { Head, Link, useForm } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmPartidaBorrador } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Pedidos', href: '/admin/almacen/pedidos' },
    { title: 'Nuevo', href: '/admin/almacen/pedidos/create' },
];

/**
 * Lo que el almacén elegido guarda. Es la lista con la que se captura: pedirle
 * a una bodega algo que otra guarda no tiene sentido, y el catálogo entero
 * ofrecía justamente eso.
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

type Props = {
    almacenes: AlmAlmacenOpcion[];
    departamentos: { id: number; descripcion: string }[];
    /** Los almacenes de obra: el pedido va a uno de éstos o se queda en planta. */
    destinos: AlmAlmacenOpcion[];
    gruposTrabajo: { id: number; descripcion: string }[];
    /** A nombre de quién puede quedar el pedido: quien tiene permiso de supervisar. */
    supervisores: { id: string; name: string }[];
};

export default function PedidoCreate({ almacenes, departamentos, destinos, gruposTrabajo, supervisores }: Props) {
    const hoy = new Date().toISOString().slice(0, 10);
    const [saldos, setSaldos] = useState<Saldo[]>([]);

    const form = useForm({
        almacen_id: '',
        departamento_id: '',
        solicitante_id: '',
        almacen_destino_id: '',
        recibe_nombre: '',
        grupo_trabajo_id: '',
        fecha: hoy,
        fecha_requerida: hoy,
        motivo: '',
        observaciones: '',
        detalles: [{ ...PARTIDA_VACIA }] as AlmPartidaBorrador[],
    });

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

    // Con almacén de obra hay que llevar el material a otro domicilio, así que lo
    // surte una transferencia y la obra confirma. Sin él se queda aquí y sale
    // directo.
    const esDeObra = form.data.almacen_destino_id !== '';

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                articulo_id: d.articulo_id,
                cantidad_solicitada: d.cantidad,
                observaciones: d.observaciones || null,
            })),
        }));

        form.post('/admin/almacen/pedidos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo pedido" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo pedido</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Lo que le pides a un almacén de lo que ya tiene. Aquí sí se puede pedir más de lo que hay: el
                        almacén decide si surte parcial o si hay que comprar.
                    </p>
                </div>

                <form onSubmit={enviar} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                                <Select
                                    id="almacen_id"
                                    value={form.data.almacen_id}
                                    onValueChange={(v) =>
                                        form.setData({
                                            ...form.data,
                                            almacen_id: v,
                                            // Pedirle a un almacén que se mande a sí mismo no es nada.
                                            almacen_destino_id:
                                                form.data.almacen_destino_id === v ? '' : form.data.almacen_destino_id,
                                        })
                                    }
                                    placeholder="¿A quién se le pide?"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Área que pide"
                                htmlFor="departamento_id"
                                error={form.errors.departamento_id}
                                description="Siempre hay un área responsable, haya obra o no."
                                required
                            >
                                <Select
                                    id="departamento_id"
                                    value={form.data.departamento_id}
                                    onValueChange={(v) => form.setData('departamento_id', v)}
                                    placeholder="¿Quién lo pide?"
                                >
                                    {departamentos.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Supervisor que pide"
                                htmlFor="solicitante_id"
                                error={form.errors.solicitante_id}
                                description="El pedido queda a su nombre, y con él se firman la salida, el préstamo o la transferencia que lo surtan."
                                required
                            >
                                <SearchSelect
                                    options={supervisores.map((u) => ({ value: u.id, label: u.name }))}
                                    value={form.data.solicitante_id}
                                    onValueChange={(v) => form.setData('solicitante_id', v)}
                                    placeholder="Escribe un nombre..."
                                    maxOptions={30}
                                />
                            </FormField>

                            <FormField
                                label="Obra destino"
                                htmlFor="almacen_destino_id"
                                error={form.errors.almacen_destino_id}
                                description="El almacén de la obra a la que va, o consumo interno si se queda en planta."
                            >
                                <Select
                                    id="almacen_destino_id"
                                    value={form.data.almacen_destino_id}
                                    onValueChange={(v) =>
                                        form.setData({
                                            ...form.data,
                                            almacen_destino_id: v,
                                            // En un pedido de obra recibe el almacén
                                            // destino, no una persona ni una cuadrilla.
                                            recibe_nombre: v === '' ? form.data.recibe_nombre : '',
                                            grupo_trabajo_id: v === '' ? form.data.grupo_trabajo_id : '',
                                        })
                                    }
                                    placeholder="Consumo interno de planta"
                                >
                                    <SelectItem value="">Consumo interno de planta</SelectItem>
                                    {destinos
                                        .filter((a) => String(a.id) !== form.data.almacen_id)
                                        .map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {etiquetaDeAlmacen(a)} — {a.nombre}
                                                {a.obra?.descripcion ? ` (${a.obra.descripcion})` : ''}
                                            </SelectItem>
                                        ))}
                                </Select>
                            </FormField>

                            {!esDeObra && (
                                <>
                                    <FormField
                                        label="A nombre de quién"
                                        htmlFor="recibe_nombre"
                                        error={form.errors.recibe_nombre}
                                        description="Opcional: el área no siempre sabe de antemano quién va a pasar por el material."
                                    >
                                        <Input
                                            id="recibe_nombre"
                                            value={form.data.recibe_nombre}
                                            onChange={(e) => form.setData('recibe_nombre', e.target.value)}
                                            placeholder="A. Pérez"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Cuadrilla"
                                        htmlFor="grupo_trabajo_id"
                                        error={form.errors.grupo_trabajo_id}
                                        description="Opcional. Sirve para saber a qué frente se fue el material."
                                    >
                                        <Select
                                            id="grupo_trabajo_id"
                                            value={form.data.grupo_trabajo_id}
                                            onValueChange={(v) => form.setData('grupo_trabajo_id', v)}
                                            placeholder="Sin cuadrilla"
                                        >
                                            <SelectItem value="">Sin cuadrilla</SelectItem>
                                            {gruposTrabajo.map((g) => (
                                                <SelectItem key={g.id} value={String(g.id)}>
                                                    {g.descripcion}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                    </FormField>
                                </>
                            )}

                            <FormField label="Fecha" htmlFor="fecha" error={form.errors.fecha} required>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={form.data.fecha}
                                    onChange={(e) => form.setData('fecha', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Se necesita el"
                                htmlFor="fecha_requerida"
                                error={form.errors.fecha_requerida}
                                required
                            >
                                <Input
                                    id="fecha_requerida"
                                    type="date"
                                    value={form.data.fecha_requerida}
                                    onChange={(e) => form.setData('fecha_requerida', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Motivo" htmlFor="motivo" error={form.errors.motivo}>
                                <Input
                                    id="motivo"
                                    value={form.data.motivo}
                                    onChange={(e) => form.setData('motivo', e.target.value)}
                                    placeholder="Montaje eje 4"
                                />
                            </FormField>

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

                    <div className="alert">
                        <InfoIcon className="size-4" />
                        <span>
                            {esDeObra
                                ? 'El material va al almacén de la obra: lo surte una transferencia, y la obra confirma cuando lo recibe.'
                                : 'El material se queda en planta: lo surte una salida directa del almacén.'}
                        </span>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Qué se pide</h2>
                        {form.errors.detalles && <p className="text-error mb-2 text-sm">{form.errors.detalles}</p>}
                        <CapturadorPartidas
                            partidas={form.data.detalles}
                            onChange={(detalles) => form.setData('detalles', detalles)}
                            articulos={saldos}
                            // Pedir de más no es un error aquí: el material
                            // todavía no sale, y el almacén decide qué hacer.
                            avisarFaltante={false}
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/pedidos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Levantar pedido
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
