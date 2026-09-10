import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmOpcion, AlmPartidaBorrador } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Ajustes', href: '/admin/almacen/ajustes' },
    { title: 'Nuevo', href: '/admin/almacen/ajustes/create' },
];

type Props = {
    almacenes: AlmAlmacenOpcion[];
    motivos: AlmOpcion[];
};

/**
 * El catálogo con el saldo que el almacén elegido tiene de cada artículo. Es a
 * la vez la lista con la que se captura y el «Sistema» contra el que se calcula
 * la diferencia.
 *
 * Aquí sí va el catálogo entero —a diferencia de la salida o la transferencia,
 * que sólo pueden mover lo que existe—: contar es justamente cómo se da de alta
 * el material que está en la bodega y el sistema todavía no conoce.
 */
type Saldo = {
    id: number;
    articulo_id: number;
    codigo: string;
    descripcion: string;
    unidad: string;
    requiere_verificacion: boolean;
    cantidad: number;
    /** Falso = el almacén no tiene renglón todavía; contarlo se lo abre. */
    en_el_almacen: boolean;
};

export default function AjusteCreate({ almacenes, motivos }: Props) {
    const form = useForm({
        almacen_id: '',
        motivo: '',
        fecha: new Date().toISOString().slice(0, 10),
        observaciones: '',
        detalles: [{ ...PARTIDA_VACIA }] as AlmPartidaBorrador[],
    });

    // El saldo se pide al cambiar de almacén, no viene con la pantalla: cambia
    // por almacén y todavía no se sabe cuál van a elegir.
    const [saldos, setSaldos] = useState<Saldo[]>([]);

    useEffect(() => {
        if (form.data.almacen_id === '') {
            setSaldos([]);

            return;
        }

        let vigente = true;

        fetch(`/admin/almacen/almacenes/${form.data.almacen_id}/catalogo-conteo`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((datos: Saldo[]) => {
                if (vigente) {
                    setSaldos(datos);
                }
            })
            .catch(() => setSaldos([]));

        // Cambiar de almacén a media captura descarta el saldo viejo: dejarlo
        // compararía lo contado aquí contra lo que hay en la otra bodega.
        return () => {
            vigente = false;
        };
    }, [form.data.almacen_id]);

    const disponibleDe = (articuloId: number) => saldos.find((s) => s.articulo_id === articuloId)?.cantidad ?? 0;

    /** Cuántos de los capturados el almacén no tenía registrados: los abre este ajuste. */
    const nuevosEnElAlmacen = form.data.detalles.filter((d) => {
        const saldo = saldos.find((s) => String(s.articulo_id) === d.articulo_id);

        return saldo !== undefined && !saldo.en_el_almacen;
    }).length;

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        // El capturador es el mismo de entradas y salidas, así que habla de
        // «cantidad». Aquí esa cantidad es lo contado, y la traducción se hace
        // al mandar en vez de tener un capturador aparte para el ajuste.
        form.transform((datos) => ({
            ...datos,
            detalles: datos.detalles.map((d) => ({
                articulo_id: d.articulo_id,
                cantidad_contada: d.cantidad,
                costo_unitario: d.costo_unitario === '' ? null : d.costo_unitario,
                observaciones: d.observaciones || null,
            })),
        }));

        form.post('/admin/almacen/ajustes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo ajuste" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo ajuste</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Captura lo que <strong>realmente hay</strong>; el sistema calcula la diferencia contra el saldo
                        y ésa es la que se graba en el kardex.
                    </p>
                </div>

                <form onSubmit={enviar} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                                <Select
                                    id="almacen_id"
                                    value={form.data.almacen_id}
                                    onValueChange={(v) => form.setData('almacen_id', v)}
                                    placeholder="¿Cuál se ajusta?"
                                >
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Motivo"
                                htmlFor="motivo"
                                error={form.errors.motivo}
                                description="Es lo que justifica mover el inventario sin un documento."
                                required
                            >
                                <Select
                                    id="motivo"
                                    value={form.data.motivo}
                                    onValueChange={(v) => form.setData('motivo', v)}
                                    placeholder="¿Por qué no cuadra?"
                                >
                                    {motivos.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha" htmlFor="fecha" error={form.errors.fecha} required>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={form.data.fecha}
                                    onChange={(e) => form.setData('fecha', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                className="md:col-span-3"
                                description="Quedan en el kardex para siempre: explica qué pasó, no sólo que faltó."
                            >
                                <Input
                                    id="observaciones"
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                    placeholder="Conteo del cierre de mes, se mojó el material..."
                                />
                            </FormField>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Conteo</h2>
                        {form.data.almacen_id === '' && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver contra qué saldo se compara cada artículo.
                            </p>
                        )}
                        {form.errors.detalles && (
                            <p className="text-error mb-2 text-sm">{form.errors.detalles}</p>
                        )}
                        <p className="text-base-content/60 mb-2 text-sm">
                            El costo unitario sólo se usa cuando <strong>sobra</strong> material: eso entra al almacén y
                            hay que decir cuánto vale. Lo que falta sale al costo promedio con el que había entrado.
                        </p>
                        {nuevosEnElAlmacen > 0 && (
                            <p className="text-info mb-2 text-sm">
                                {nuevosEnElAlmacen === 1
                                    ? 'Un artículo capturado no estaba registrado en este almacén: el ajuste le abre existencia.'
                                    : `${nuevosEnElAlmacen} artículos capturados no estaban registrados en este almacén: el ajuste les abre existencia.`}
                            </p>
                        )}
                        <CapturadorPartidas
                            partidas={form.data.detalles}
                            onChange={(detalles) => form.setData('detalles', detalles)}
                            articulos={saldos}
                            modo="conteo"
                            conCosto
                            disponibleDe={form.data.almacen_id === '' ? undefined : disponibleDe}
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/ajustes">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Guardar ajuste
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
