import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FechasMovimiento } from '@/components/alm/fechas-movimiento';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, disponibleDemo } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowRightIcon, TruckIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
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
                    <p className="text-base-content/60 mt-1 max-w-3xl text-sm">
                        Este es el <strong>primer tiempo</strong>: el envío. Lo que se capture aquí sale del almacén
                        origen y queda en tránsito —no suma en el destino todavía—. El mismo folio se firma otra vez
                        cuando la obra confirme qué llegó.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <div className="alert alert-info mb-4">
                    <TruckIcon className="size-5" />
                    <span>
                        Planta y obra están a kilómetros, así que el documento no se cierra de un golpe: el destino
                        puede confirmar menos de lo enviado y la diferencia queda como faltante con dueño y fecha.
                    </span>
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

                            <FechasMovimiento fecha={fecha} onChange={setFecha} label="Fecha del envío" />
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
                        <p className="text-base-content/60 mb-3 text-sm">
                            Lo que se manda. Contra esto va a confirmar el destino, renglón por renglón.
                        </p>
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
                            Registrar envío
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
