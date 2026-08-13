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
import { ArrowRightIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
    { title: 'Nueva', href: '/admin/almacen/transferencias/create' },
];

export default function TransferenciaCreate() {
    const [origenId, setOrigenId] = useState('');
    const [destinoId, setDestinoId] = useState('');
    const [fecha, setFecha] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveOrigen = ALMACENES_DEMO.find((a) => String(a.id) === origenId)?.clave;
    const mismoAlmacen = origenId !== '' && origenId === destinoId;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva transferencia" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva transferencia</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se registra como una sola operación: lo que sale del origen entra al destino en el mismo
                        instante, nunca a medias.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 items-end gap-4 md:grid-cols-[1fr_auto_1fr_1fr]">
                            <FormField label="Almacén origen" htmlFor="origen" required>
                                <Select
                                    id="origen"
                                    value={origenId}
                                    onValueChange={setOrigenId}
                                    placeholder="¿De dónde sale?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <ArrowRightIcon className="text-base-content/40 mb-3 hidden size-5 md:block" />

                            <FormField
                                label="Almacén destino"
                                htmlFor="destino"
                                error={mismoAlmacen ? 'El destino tiene que ser otro almacén.' : undefined}
                                required
                            >
                                <Select
                                    id="destino"
                                    value={destinoId}
                                    onValueChange={setDestinoId}
                                    placeholder="¿A dónde llega?"
                                    error={mismoAlmacen}
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
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
                        </div>

                        <FormField label="Observaciones" htmlFor="observaciones" className="mt-4">
                            <textarea
                                id="observaciones"
                                className="textarea textarea-bordered w-full"
                                rows={2}
                                value={observaciones}
                                onChange={(e) => setObservaciones(e.target.value)}
                                placeholder="Quién autoriza, transporte, motivo del movimiento"
                            />
                        </FormField>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Partidas</h2>
                        <CapturadorPartidas
                            partidas={partidas}
                            onChange={setPartidas}
                            disponibleDe={claveOrigen ? (codigo) => disponibleDemo(claveOrigen, codigo) : undefined}
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/transferencias">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar transferencia
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
