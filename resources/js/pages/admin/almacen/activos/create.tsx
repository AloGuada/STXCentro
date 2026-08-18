import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, ARTICULOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon, PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Alta', href: '/admin/almacen/activos/create' },
];

/** Sólo lo marcado "por pieza" en el catálogo se puede serializar. */
const SERIALIZABLES = ARTICULOS_DEMO.filter((a) => a.se_controla_por_pieza);

/**
 * Lo que se captura de cada pieza. La serie es la que la identifica; marca,
 * modelo e id de mantenimiento la acompañan como información suya y no del
 * artículo: el catálogo dice qué es —una pulidora de 4 1/2"— y esto, con qué
 * se cumplió.
 */
type Pieza = {
    serie: string;
    marca: string;
    modelo: string;
    id_mantenimiento: string;
};

const PIEZA_VACIA: Pieza = { serie: '', marca: '', modelo: '', id_mantenimiento: '' };

export default function ActivoCreate() {
    const [articuloId, setArticuloId] = useState('');
    const [almacenId, setAlmacenId] = useState('');
    const [condicion, setCondicion] = useState('Buena');
    const [costo, setCosto] = useState('');
    const [fechaAlta, setFechaAlta] = useState('');
    const [piezas, setPiezas] = useState<Pieza[]>([{ ...PIEZA_VACIA }]);
    // La carga inicial son decenas de piezas: teclear renglón por renglón hace
    // que nadie la termine, así que se pueden pegar las series de golpe y
    // corregir después la que se salga.
    const [pegado, setPegado] = useState('');

    const articulo = SERIALIZABLES.find((a) => String(a.id) === articuloId);

    const cambiar = (indice: number, campo: keyof Pieza, valor: string) =>
        setPiezas((prev) => prev.map((p, i) => (i === indice ? { ...p, [campo]: valor } : p)));

    const agregar = () => setPiezas((prev) => [...prev, { ...PIEZA_VACIA }]);

    const quitar = (indice: number) =>
        setPiezas((prev) => (prev.length === 1 ? [{ ...PIEZA_VACIA }] : prev.filter((_, i) => i !== indice)));

    /**
     * Cada renglón pegado es una pieza. Hereda marca y modelo del último
     * renglón que ya los traiga: un alta suele ser del mismo lote, y volver a
     * teclear «DeWalt» treinta veces es lo que hace que se dejen en blanco.
     */
    const agregarPegadas = () => {
        const series = pegado
            .split('\n')
            .map((s) => s.trim())
            .filter(Boolean);

        if (series.length === 0) {
            return;
        }

        setPiezas((prev) => {
            const ultima = [...prev].reverse().find((p) => p.marca || p.modelo);
            const nuevas = series.map((serie) => ({
                ...PIEZA_VACIA,
                serie,
                marca: ultima?.marca ?? '',
                modelo: ultima?.modelo ?? '',
            }));

            // El renglón vacío inicial estorba en cuanto hay pegadas.
            return [...prev.filter((p) => p.serie.trim() !== ''), ...nuevas];
        });

        setPegado('');
    };

    const { limpias, repetidas } = useMemo(() => {
        const vistas = new Set<string>();
        const repes = new Set<string>();

        piezas.forEach((p) => {
            const serie = p.serie.trim();

            if (serie === '') {
                return;
            }

            if (vistas.has(serie)) {
                repes.add(serie);
            }

            vistas.add(serie);
        });

        return { limpias: [...vistas], repetidas: [...repes] };
    }, [piezas]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Alta de activos" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Alta de activos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Da de alta las piezas de un artículo, una por número de serie. Varias a la vez: es lo que se
                        necesita para cargar el almacén la primera vez.
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

                            <FormField label="Almacén" htmlFor="almacen" required>
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

                        <div className="mt-4">
                            <div className="mb-2 flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h2 className="font-medium">Piezas</h2>
                                    <p className="text-base-content/60 text-sm">
                                        Una por renglón. La serie es la que la identifica; marca, modelo e id de
                                        mantenimiento son de la pieza —no del artículo— y pueden quedarse en blanco.
                                    </p>
                                </div>
                                <Button type="button" variant="outline" size="sm" onClick={agregar}>
                                    <PlusIcon className="size-4" />
                                    Agregar renglón
                                </Button>
                            </div>

                            <div className="rounded-box border-base-300 overflow-x-auto border">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th className="w-10">#</th>
                                            <th>
                                                No. de serie <span className="text-error">*</span>
                                            </th>
                                            <th>Marca</th>
                                            <th>Modelo</th>
                                            <th>Id de mantenimiento</th>
                                            <th className="w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {piezas.map((pieza, i) => {
                                            const repetida =
                                                pieza.serie.trim() !== '' && repetidas.includes(pieza.serie.trim());

                                            return (
                                                <tr key={i}>
                                                    <td className="text-base-content/40 text-xs">{i + 1}</td>
                                                    <td>
                                                        <Input
                                                            value={pieza.serie}
                                                            onChange={(e) => cambiar(i, 'serie', e.target.value)}
                                                            className={`input-sm font-mono ${repetida ? 'input-error' : ''}`}
                                                            placeholder="PUL-4120-01"
                                                            aria-label={`Serie de la pieza ${i + 1}`}
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            value={pieza.marca}
                                                            onChange={(e) => cambiar(i, 'marca', e.target.value)}
                                                            className="input-sm"
                                                            placeholder="DeWalt"
                                                            aria-label={`Marca de la pieza ${i + 1}`}
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            value={pieza.modelo}
                                                            onChange={(e) => cambiar(i, 'modelo', e.target.value)}
                                                            className="input-sm"
                                                            placeholder="DWE4120"
                                                            aria-label={`Modelo de la pieza ${i + 1}`}
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            value={pieza.id_mantenimiento}
                                                            onChange={(e) =>
                                                                cambiar(i, 'id_mantenimiento', e.target.value)
                                                            }
                                                            className="input-sm font-mono"
                                                            placeholder="MTO-0071"
                                                            aria-label={`Id de mantenimiento de la pieza ${i + 1}`}
                                                        />
                                                    </td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            onClick={() => quitar(i)}
                                                            title="Quitar este renglón"
                                                            aria-label={`Quitar la pieza ${i + 1}`}
                                                        >
                                                            <Trash2Icon className="size-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* La carga inicial son decenas de piezas del mismo lote: se pegan
                                las series y después se corrige el renglón que se salga. */}
                            <details className="collapse-arrow border-base-300 rounded-box collapse mt-3 border">
                                <summary className="collapse-title text-sm font-medium">
                                    Pegar varias series de golpe
                                </summary>
                                <div className="collapse-content">
                                    <textarea
                                        className="textarea textarea-bordered w-full font-mono"
                                        rows={5}
                                        value={pegado}
                                        onChange={(e) => setPegado(e.target.value)}
                                        placeholder={'PUL-4120-01\nPUL-4120-02\nPUL-4120-03'}
                                        aria-label="Series para agregar de golpe"
                                    />
                                    <div className="mt-2 flex items-center justify-between gap-2">
                                        <span className="text-base-content/60 text-sm">
                                            Una serie por renglón. Heredan la marca y el modelo del último renglón que
                                            los traiga.
                                        </span>
                                        <Button type="button" variant="outline" size="sm" onClick={agregarPegadas}>
                                            Agregar a la lista
                                        </Button>
                                    </div>
                                </div>
                            </details>
                        </div>

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
