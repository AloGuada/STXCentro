import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, comoSeSurte, DEPARTAMENTOS_DEMO, disponibleDemo } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Pedidos', href: '/admin/almacen/pedidos' },
    { title: 'Nuevo', href: '/admin/almacen/pedidos/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

type Destino = 'obra' | 'planta';

export default function PedidoCreate() {
    const [almacenId, setAlmacenId] = useState('');
    // La planta también pide para sí misma: fabricación, pintura y
    // mantenimiento consumen material y no cuelgan de ninguna obra. Antes el
    // formulario exigía obra, así que ese pedido no se podía levantar.
    const [destino, setDestino] = useState<Destino>('obra');
    const [obra, setObra] = useState('');
    const [departamento, setDepartamento] = useState('');
    const [fechaRequerida, setFechaRequerida] = useState('');
    const [motivo, setMotivo] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;
    const nombreObra = OBRAS_DEMO.find((o) => String(o.id) === obra)?.etiqueta ?? null;
    const surtido = comoSeSurte(destino === 'obra' ? nombreObra : null);

    /** Cambiar de destino limpia el otro campo: sólo uno de los dos aplica. */
    const elegirDestino = (valor: Destino) => {
        setDestino(valor);
        setObra('');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo pedido" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo pedido</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Pide material a un almacén. Se puede pedir más de lo que hay: el almacén decide si surte
                        parcial o si hay que comprar.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Le pide a" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacenId}
                                    onValueChange={setAlmacenId}
                                    placeholder="¿Qué almacén surte?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Área que lo pide" htmlFor="departamento" required>
                                <Select
                                    id="departamento"
                                    value={departamento}
                                    onValueChange={setDepartamento}
                                    placeholder="¿Quién lo necesita?"
                                >
                                    {DEPARTAMENTOS_DEMO.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Requerido para"
                                htmlFor="fecha_requerida"
                                description="Cuándo se necesita."
                                required
                            >
                                <Input
                                    id="fecha_requerida"
                                    type="date"
                                    value={fechaRequerida}
                                    onChange={(e) => setFechaRequerida(e.target.value)}
                                />
                            </FormField>
                        </div>

                        <fieldset className="mt-4">
                            <legend className="mb-2 text-sm font-medium">¿Para dónde es el material?</legend>
                            <div className="flex flex-wrap gap-4">
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'obra'}
                                        onChange={() => elegirDestino('obra')}
                                    />
                                    <span className="text-sm">Para una obra</span>
                                </label>
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="destino"
                                        className="radio radio-sm"
                                        checked={destino === 'planta'}
                                        onChange={() => elegirDestino('planta')}
                                    />
                                    <span className="text-sm">Consumo interno de planta</span>
                                </label>
                            </div>
                        </fieldset>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            {destino === 'obra' ? (
                                <FormField label="Obra destino" htmlFor="obra" required>
                                    <Select
                                        id="obra"
                                        value={obra}
                                        onValueChange={setObra}
                                        placeholder="¿A qué obra va?"
                                    >
                                        {OBRAS_DEMO.map((o) => (
                                            <SelectItem key={o.id} value={String(o.id)}>
                                                {o.etiqueta}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            ) : (
                                <div className="text-base-content/60 self-end pb-3 text-sm md:col-span-1">
                                    El material se consume en la planta, sin cargarse a ninguna obra.
                                </div>
                            )}

                            <FormField label="Motivo" htmlFor="motivo" className="md:col-span-2" required>
                                <Input
                                    id="motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder={
                                        destino === 'obra'
                                            ? 'Montaje eje 4, sellado de fachada norte...'
                                            : 'Habilitado de placa, retoque de pintura...'
                                    }
                                />
                            </FormField>
                        </div>

                        {/*
                         * Que se vea desde la captura con qué documento se va a
                         * surtir: es lo que decide si la obra tiene que confirmar
                         * la recepción o si el material sale y ya.
                         */}
                        <div className="alert alert-info mt-4">
                            <InfoIcon className="size-5" />
                            <span>
                                Se surtirá con una <strong>{surtido.documento.toLowerCase()}</strong>.{' '}
                                {surtido.explicacion}
                            </span>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Partidas</h2>
                        {!claveAlmacen && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver qué tiene disponible de cada producto.
                            </p>
                        )}
                        <CapturadorPartidas
                            partidas={partidas}
                            onChange={setPartidas}
                            disponibleDe={claveAlmacen ? (codigo) => disponibleDemo(claveAlmacen, codigo) : undefined}
                            avisarFaltante={false}
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/pedidos">Cancelar</Link>
                        </Button>
                        <Button variant="outline" type="submit" disabled>
                            Guardar borrador
                        </Button>
                        <Button type="submit" disabled>
                            Enviar a aprobación
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
