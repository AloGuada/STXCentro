import { CapturadorPartidas, PARTIDA_VACIA } from '@/components/alm/capturador-partidas';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, MOTIVOS_AJUSTE, disponibleDemo } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmAjusteMotivo, AlmPartidaBorrador } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Ajustes', href: '/admin/almacen/ajustes' },
    { title: 'Nuevo', href: '/admin/almacen/ajustes/create' },
];

export default function AjusteCreate() {
    const [almacenId, setAlmacenId] = useState('');
    const [motivo, setMotivo] = useState('');
    const [fecha, setFecha] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [partidas, setPartidas] = useState<AlmPartidaBorrador[]>([{ ...PARTIDA_VACIA }]);

    const claveAlmacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId)?.clave;

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
                                    placeholder="¿Cuál se ajusta?"
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
                                label="Motivo"
                                htmlFor="motivo"
                                description="Es lo que justifica mover el inventario sin un documento."
                                required
                            >
                                <Select
                                    id="motivo"
                                    value={motivo}
                                    onValueChange={setMotivo}
                                    placeholder="¿Por qué no cuadra?"
                                >
                                    {Object.entries(MOTIVOS_AJUSTE).map(([valor, etiqueta]) => (
                                        <SelectItem key={valor} value={valor as AlmAjusteMotivo}>
                                            {etiqueta}
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
                                label="Observaciones"
                                htmlFor="observaciones"
                                className="md:col-span-3"
                                description="Quedan en el kardex para siempre: explica qué pasó, no sólo que faltó."
                            >
                                <Input
                                    id="observaciones"
                                    value={observaciones}
                                    onChange={(e) => setObservaciones(e.target.value)}
                                    placeholder="Conteo del cierre de mes, se mojó el material..."
                                />
                            </FormField>
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-semibold">Conteo</h2>
                        {!claveAlmacen && (
                            <p className="text-base-content/60 mb-2 text-sm">
                                Elige el almacén para ver contra qué saldo se compara cada producto.
                            </p>
                        )}
                        <CapturadorPartidas
                            partidas={partidas}
                            onChange={setPartidas}
                            modo="conteo"
                            disponibleDe={claveAlmacen ? (codigo) => disponibleDemo(claveAlmacen, codigo) : undefined}
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/ajustes">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar ajuste
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
