import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, ARTICULOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Alta', href: '/admin/almacen/activos/create' },
];

/** Sólo lo marcado "por pieza" en el catálogo se puede serializar. */
const SERIALIZABLES = ARTICULOS_DEMO.filter((a) => a.se_controla_por_pieza);

export default function ActivoCreate() {
    const [articuloId, setArticuloId] = useState('');
    const [almacenId, setAlmacenId] = useState('');
    const [condicion, setCondicion] = useState('Buena');
    const [costo, setCosto] = useState('');
    const [fechaAlta, setFechaAlta] = useState('');
    // La carga inicial son decenas de piezas del mismo modelo: pedirlas una por
    // una con el formulario completo hace que nadie la termine.
    const [series, setSeries] = useState('');

    const articulo = SERIALIZABLES.find((a) => String(a.id) === articuloId);

    const { limpias, repetidas } = useMemo(() => {
        const lista = series
            .split('\n')
            .map((s) => s.trim())
            .filter(Boolean);

        const vistas = new Set<string>();
        const repes = new Set<string>();

        lista.forEach((s) => {
            if (vistas.has(s)) {
                repes.add(s);
            }

            vistas.add(s);
        });

        return { limpias: [...vistas], repetidas: [...repes] };
    }, [series]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Alta de activos" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Alta de activos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Da de alta las piezas de un artículo, una por número de serie. Varias a la vez: es lo que se
                        necesita para cargar el pañol la primera vez.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                label="Artículo"
                                htmlFor="articulo"
                                description="Sólo los marcados «por pieza» en el catálogo."
                                required
                            >
                                <Select
                                    id="articulo"
                                    value={articuloId}
                                    onValueChange={setArticuloId}
                                    placeholder="¿Qué se da de alta?"
                                >
                                    {SERIALIZABLES.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.codigo} — {a.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Pañol" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacenId}
                                    onValueChange={setAlmacenId}
                                    placeholder="¿Dónde viven?"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha de alta" htmlFor="fecha_alta" required>
                                <Input
                                    id="fecha_alta"
                                    type="date"
                                    value={fechaAlta}
                                    onChange={(e) => setFechaAlta(e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Condición"
                                htmlFor="condicion"
                                description="Se aplica a todas las piezas de esta alta; después se ajusta una por una."
                                required
                            >
                                <Input
                                    id="condicion"
                                    value={condicion}
                                    onChange={(e) => setCondicion(e.target.value)}
                                    placeholder="Buena, usada, sin guarda..."
                                />
                            </FormField>

                            <FormField
                                label="Costo por pieza"
                                htmlFor="costo"
                                description="Opcional. Es lo que se cobra si se pierde."
                            >
                                <Input
                                    id="costo"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={costo}
                                    onChange={(e) => setCosto(e.target.value)}
                                    placeholder="0.00"
                                />
                            </FormField>
                        </div>

                        <FormField
                            label="Números de serie"
                            htmlFor="series"
                            description="Uno por renglón. Se crea una pieza por cada uno."
                            className="mt-4"
                            required
                        >
                            <textarea
                                id="series"
                                className="textarea textarea-bordered w-full font-mono"
                                rows={8}
                                value={series}
                                onChange={(e) => setSeries(e.target.value)}
                                placeholder={'PUL-4120-01\nPUL-4120-02\nPUL-4120-03'}
                            />
                        </FormField>

                        {repetidas.length > 0 && (
                            <div className="alert alert-warning mt-3">
                                <TriangleAlertIcon className="size-5" />
                                <span>
                                    Series repetidas en la lista, se dará de alta una sola vez de cada una:{' '}
                                    <span className="font-mono">{repetidas.join(', ')}</span>
                                </span>
                            </div>
                        )}

                        {limpias.length > 0 && articulo && (
                            <div className="alert alert-info mt-3">
                                <InfoIcon className="size-5" />
                                <span>
                                    Se darán de alta <strong>{limpias.length}</strong>{' '}
                                    {limpias.length === 1 ? 'pieza' : 'piezas'} de {articulo.codigo}. La existencia del
                                    artículo sube en {limpias.length}: cada pieza suma 1 al kardex, no es un inventario
                                    aparte.
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/activos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled title="La maqueta todavía no guarda">
                            Dar de alta {limpias.length > 0 ? `(${limpias.length})` : ''}
                        </Button>
                    </div>
                </form>

                <p className="text-base-content/60 mt-4 text-sm">
                    Si un artículo no aparece en la lista, primero hay que marcarlo{' '}
                    <strong>por pieza</strong> en{' '}
                    <Link href="/admin/almacen/articulos" className="link">
                        Artículos
                    </Link>
                    . Serializar un insumo no tiene sentido —se gasta—, por eso el catálogo lo decide primero.
                </p>
            </div>
        </AppLayout>
    );
}
