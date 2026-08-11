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
    { title: 'Requisiciones', href: '/admin/almacen/requisiciones' },
    { title: 'Nueva', href: '/admin/almacen/requisiciones/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

export default function RequisicionCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [obra, setObra] = useState('');
    const [fechaRequerida, setFechaRequerida] = useState('');
    const [motivo, setMotivo] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva requisición" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva requisición</h1>
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

                            <FormField label="Obra que lo pide" htmlFor="obra" required>
                                <Select id="obra" value={obra} onValueChange={setObra} placeholder="¿Para dónde es?">
                                    {OBRAS_DEMO.map((o) => (
                                        <SelectItem key={o.id} value={String(o.id)}>
                                            {o.etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Requerido para"
                                htmlFor="fecha_requerida"
                                description="Cuándo se necesita en obra."
                                required
                            >
                                <Input
                                    id="fecha_requerida"
                                    type="date"
                                    value={fechaRequerida}
                                    onChange={(e) => setFechaRequerida(e.target.value)}
                                />
                            </FormField>

                            <FormField label="Motivo" htmlFor="motivo" className="md:col-span-3" required>
                                <Input
                                    id="motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder="Montaje eje 4, sellado de fachada norte..."
                                />
                            </FormField>
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
                            <Link href="/admin/almacen/requisiciones">Cancelar</Link>
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
