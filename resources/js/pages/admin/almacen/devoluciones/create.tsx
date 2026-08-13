import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Devoluciones', href: '/admin/almacen/devoluciones' },
    { title: 'Nueva', href: '/admin/almacen/devoluciones/create' },
];

const OBRAS_DEMO = [
    { id: 1, etiqueta: 'T4 — Torre 4' },
    { id: 2, etiqueta: 'MBP — Museo Bellas Artes' },
];

export default function DevolucionCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [obra, setObra] = useState('');
    const [devolvio, setDevolvio] = useState('');
    const [fecha, setFecha] = useState('');
    const [motivo, setMotivo] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva devolución" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva devolución</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Material que vuelve de la obra. Suma a la existencia del almacén que lo recibe, igual que una
                        entrada, pero el kardex lo distingue para poder medir cuánto se pidió de más.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Almacén que recibe" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacenId}
                                    onValueChange={setAlmacenId}
                                    placeholder="¿A dónde regresa?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Obra de origen" htmlFor="obra" required>
                                <Select id="obra" value={obra} onValueChange={setObra} placeholder="¿De dónde viene?">
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
                                label="Devolvió"
                                htmlFor="devolvio"
                                description="Quién trae el material de vuelta."
                                required
                            >
                                <Input
                                    id="devolvio"
                                    value={devolvio}
                                    onChange={(e) => setDevolvio(e.target.value)}
                                    placeholder="Cuadrilla 3, A. Pérez..."
                                />
                            </FormField>

                            <FormField label="Motivo" htmlFor="motivo" className="md:col-span-2" required>
                                <Input
                                    id="motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder="Sobrante de montaje, material equivocado..."
                                />
                            </FormField>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Partidas</h2>
                        <CapturadorPartidas partidas={partidas} onChange={setPartidas} />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/devoluciones">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar devolución
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
