import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { TIPOS_INSUMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmProductoTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Insumos', href: '/admin/almacen/insumos' },
    { title: 'Nuevo', href: '/admin/almacen/insumos/create' },
];

const UNIDADES_DEMO = ['PZA', 'KG', 'LTS', 'MTS', 'PAR', 'CTO', 'SRV'];

export default function InsumoCreate() {
    const [codigo, setCodigo] = useState('');
    const [descripcion, setDescripcion] = useState('');
    const [unidad, setUnidad] = useState('');
    const [tipo, setTipo] = useState<AlmProductoTipo>('insumo');
    const [requiereVerificacion, setRequiereVerificacion] = useState(false);
    const [controlaInventario, setControlaInventario] = useState(true);
    const [stockMinimo, setStockMinimo] = useState('');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo insumo" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo insumo</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se da de alta en el catálogo que comparten Compras y Almacén: el mismo código sirve para
                        cotizar y para el kardex.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="max-w-3xl space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField label="Código" htmlFor="codigo" required>
                                <Input
                                    id="codigo"
                                    value={codigo}
                                    onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                                    placeholder="TOR-0012"
                                    className="font-mono"
                                />
                            </FormField>

                            <FormField label="Unidad" htmlFor="unidad" required>
                                <Select id="unidad" value={unidad} onValueChange={setUnidad} placeholder="¿En qué se mide?">
                                    {UNIDADES_DEMO.map((u) => (
                                        <SelectItem key={u} value={u}>
                                            {u}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Descripción" htmlFor="descripcion" className="md:col-span-2" required>
                                <Input
                                    id="descripcion"
                                    value={descripcion}
                                    onChange={(e) => setDescripcion(e.target.value)}
                                    placeholder='Tornillo A325 3/4" x 2"'
                                />
                            </FormField>

                            <FormField
                                label="Tipo"
                                htmlFor="tipo"
                                description="Define si se gasta o si sale y regresa."
                                required
                            >
                                <Select id="tipo" value={tipo} onValueChange={(v) => setTipo(v as AlmProductoTipo)}>
                                    {Object.entries(TIPOS_INSUMO).map(([valor, etiqueta]) => (
                                        <SelectItem key={valor} value={valor}>
                                            {etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Stock mínimo"
                                htmlFor="stock_minimo"
                                description="Opcional. Debajo de esto, la existencia se marca en rojo."
                            >
                                <Input
                                    id="stock_minimo"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    value={stockMinimo}
                                    onChange={(e) => setStockMinimo(e.target.value)}
                                    placeholder="0"
                                    disabled={!controlaInventario}
                                />
                            </FormField>

                            <div className="md:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={requiereVerificacion}
                                        onChange={(e) => setRequiereVerificacion(e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-medium">Verifica recepción</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Márcalo para el equipo cuyo mantenimiento hay que revisar al recibirlo (una
                                            pulidora sí, un andamio no). La entrada queda detenida hasta que alguien
                                            palomee esa revisión.
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={controlaInventario}
                                        onChange={(e) => setControlaInventario(e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-medium">Lleva kardex</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Desmárcalo para lo que se compra pero no se almacena (fletes, servicios,
                                            maniobras). Sin kardex no aparece en existencias ni en los movimientos.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/insumos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar insumo
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
