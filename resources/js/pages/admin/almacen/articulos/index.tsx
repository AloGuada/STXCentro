import { Button, ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ARTICULOS_DEMO, TIPOS_ARTICULO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmArticuloDemo, AlmProductoTipo } from '@/types/models';
import { Head } from '@inertiajs/react';
import { ImageIcon, PlusIcon, SearchIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Artículos', href: '/admin/almacen/articulos' },
];

const CLASE_TIPO: Record<AlmProductoTipo, string> = {
    insumo: 'badge-ghost',
    activo: 'badge-warning',
};

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/** La foto ayuda a reconocer el artículo; sin ella queda el hueco marcado. */
function MiniaturaArticulo({ articulo }: { articulo: AlmArticuloDemo }) {
    if (!articulo.imagen_url) {
        return (
            <div
                className="border-base-300 text-base-content/30 flex size-10 items-center justify-center rounded border border-dashed"
                title="Sin imagen"
            >
                <ImageIcon className="size-4" />
            </div>
        );
    }

    return (
        <img
            src={articulo.imagen_url}
            alt={articulo.descripcion}
            className="border-base-300 size-10 rounded border object-cover"
        />
    );
}

export default function ArticulosIndex() {
    const [query, setQuery] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('');
    // La clasificación se edita en la propia lista: es lo que se viene a hacer
    // aquí, y obligar a entrar renglón por renglón lo volvería inservible.
    const [cambios, setCambios] = useState<Record<number, Partial<AlmArticuloDemo>>>({});

    const valorDe = (articulo: AlmArticuloDemo): AlmArticuloDemo => ({ ...articulo, ...cambios[articulo.id] });

    const editar = (id: number, cambio: Partial<AlmArticuloDemo>) =>
        setCambios((prev) => ({ ...prev, [id]: { ...prev[id], ...cambio } }));

    const s = query.trim().toLowerCase();
    const visibles = ARTICULOS_DEMO.map(valorDe).filter(
        (i) =>
            (s === '' || i.codigo.toLowerCase().includes(s) || i.descripcion.toLowerCase().includes(s)) &&
            (filtroTipo === '' || i.tipo === filtroTipo),
    );

    const pendientes = Object.keys(cambios).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Artículos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Artículos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            El mismo catálogo que usa Compras, clasificado desde almacén: qué se gasta, qué se presta y
                            qué ni siquiera se guarda.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/articulos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo artículo
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <label className="input input-bordered flex max-w-sm flex-1 items-center gap-2">
                        <SearchIcon className="text-base-content/50 size-4" />
                        <input
                            className="grow"
                            placeholder="Buscar por código o descripción..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </label>

                    <div className="w-56">
                        <Select value={filtroTipo} onValueChange={setFiltroTipo} placeholder="Todos los tipos">
                            {Object.entries(TIPOS_ARTICULO).map(([valor, etiqueta]) => (
                                <SelectItem key={valor} value={valor}>
                                    {etiqueta}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <Button disabled={pendientes === 0} title="La maqueta no guarda todavía">
                        Guardar cambios {pendientes > 0 ? `(${pendientes})` : ''}
                    </Button>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th className="w-14"></th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th className="w-40">Tipo</th>
                                <th className="text-center">Verifica recepción</th>
                                <th className="text-center">Lleva kardex</th>
                                <th className="text-right">Stock mínimo</th>
                                <th className="text-right">Existencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-base-content/50 py-6 text-center">
                                        Ningún artículo coincide con el filtro.
                                    </td>
                                </tr>
                            ) : (
                                visibles.map((i) => {
                                    const bajoMinimo =
                                        i.controla_inventario &&
                                        i.stock_minimo !== null &&
                                        i.existencia_total < i.stock_minimo;

                                    return (
                                        <tr key={i.id} className="hover">
                                            <td>
                                                <MiniaturaArticulo articulo={i} />
                                            </td>
                                            <td className="font-mono font-medium">{i.codigo}</td>
                                            <td>
                                                {i.descripcion}
                                                <span className={`badge badge-xs ml-2 ${CLASE_TIPO[i.tipo]}`}>
                                                    {TIPOS_ARTICULO[i.tipo]}
                                                </span>
                                            </td>
                                            <td className="text-base-content/60 font-mono text-xs">{i.unidad}</td>
                                            <td>
                                                <Select
                                                    value={i.tipo}
                                                    onValueChange={(v) => editar(i.id, { tipo: v as AlmProductoTipo })}
                                                    className="select-sm"
                                                >
                                                    {Object.entries(TIPOS_ARTICULO).map(([valor, etiqueta]) => (
                                                        <SelectItem key={valor} value={valor}>
                                                            {etiqueta}
                                                        </SelectItem>
                                                    ))}
                                                </Select>
                                            </td>
                                            <td className="text-center">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-sm"
                                                    checked={i.requiere_verificacion}
                                                    onChange={(e) =>
                                                        editar(i.id, { requiere_verificacion: e.target.checked })
                                                    }
                                                    aria-label={`Verifica recepción ${i.codigo}`}
                                                />
                                            </td>
                                            <td className="text-center">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-sm"
                                                    checked={i.controla_inventario}
                                                    onChange={(e) =>
                                                        editar(i.id, { controla_inventario: e.target.checked })
                                                    }
                                                    aria-label={`Lleva kardex ${i.codigo}`}
                                                />
                                            </td>
                                            <td className="text-right font-mono">
                                                {i.stock_minimo === null ? (
                                                    <span className="text-base-content/40">—</span>
                                                ) : (
                                                    numero(i.stock_minimo)
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {i.controla_inventario ? (
                                                    <span className={bajoMinimo ? 'text-error font-semibold' : ''}>
                                                        {bajoMinimo && (
                                                            <TriangleAlertIcon className="mr-1 inline size-3" />
                                                        )}
                                                        {numero(i.existencia_total)}
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/40">No aplica</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    El <strong>insumo</strong> se gasta y sólo se cuenta. El <strong>activo</strong> sale y regresa:
                    hoy se maneja por cantidad, y más adelante cada pieza tendrá su número de serie y su resguardo.{' '}
                    <strong>Verifica recepción</strong> detiene la entrada hasta que alguien revise el mantenimiento
                    del equipo, y va aparte porque no todo activo lo necesita. Quitar <strong>lleva kardex</strong> es
                    para lo que se compra pero no se almacena, como un flete.
                </p>
            </div>
        </AppLayout>
    );
}
