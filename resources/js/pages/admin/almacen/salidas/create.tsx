import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, disponibleDemo } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Salidas', href: '/admin/almacen/salidas' },
    { title: 'Nuevo vale', href: '/admin/almacen/salidas/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

export default function SalidaCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [obra, setObra] = useState('');
    const [recibe, setRecibe] = useState('');
    const [fecha, setFecha] = useState('');
    const [motivo, setMotivo] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo vale de salida" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo vale de salida</h1>
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
                                    onValueChange={setAlmacenId}
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
                                description="Nombre de quien se lleva el material; es el que firma el vale."
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
                            Guardar vale
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
