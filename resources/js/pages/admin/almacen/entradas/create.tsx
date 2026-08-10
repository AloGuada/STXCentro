import { CapturadorPartidas, PARTIDA_VACIA, partidasSinVerificar } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
    { title: 'Nueva', href: '/admin/almacen/entradas/create' },
];

const PROVEEDORES_DEMO = [
    { id: 1, nombre: 'Aceros del Norte S.A.' },
    { id: 2, nombre: 'Soldaduras Industriales' },
    { id: 3, nombre: 'Selladores del Golfo' },
];

export default function EntradaCreate() {
    const [almacen, setAlmacen] = useState('');
    const [proveedor, setProveedor] = useState('');
    const [fecha, setFecha] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const sinVerificar = partidasSinVerificar(partidas);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva entrada" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva entrada</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Lo que capture aquí sube la existencia del almacén. El costo es opcional, pero sin él el
                        inventario no se puede valuar. Lo que pide verificación no se recepciona sin revisar su
                        mantenimiento.
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
                                    value={almacen}
                                    onValueChange={setAlmacen}
                                    placeholder="¿A dónde entra?"
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
                                label="Proveedor"
                                htmlFor="proveedor"
                                description="Opcional: material que regresa de obra no trae proveedor."
                            >
                                <Select
                                    id="proveedor"
                                    value={proveedor}
                                    onValueChange={setProveedor}
                                    placeholder="Sin proveedor"
                                >
                                    {PROVEEDORES_DEMO.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
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
                                placeholder="Remisión, número de factura, quién entregó..."
                            />
                        </FormField>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Partidas</h2>
                        <CapturadorPartidas
                            partidas={partidas}
                            onChange={setPartidas}
                            conCosto
                            pedirVerificacionMantenimiento
                        />
                    </div>

                    {sinVerificar.length > 0 && (
                        <div className="alert alert-error">
                            <TriangleAlertIcon className="size-5" />
                            <span>
                                No se puede recepcionar: {sinVerificar.length}{' '}
                                {sinVerificar.length === 1 ? 'equipo' : 'equipos'} sin verificación de mantenimiento.
                            </span>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/entradas">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled title="La maqueta todavía no guarda">
                            Guardar entrada
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
