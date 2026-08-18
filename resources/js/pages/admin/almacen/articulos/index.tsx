import { MiniaturaArticulo } from '@/components/alm/miniatura-articulo';
import { Button, ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ARTICULOS_DEMO, CLASES_ABC, REGLAS_ABC, TIPOS_ARTICULO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmArticuloDemo, AlmProductoTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { BarcodeIcon, PencilIcon, PlusIcon, SearchIcon, TagIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Artículos', href: '/admin/almacen/articulos' },
];

const CLASE_TIPO: Record<AlmProductoTipo, string> = {
    insumo: 'badge-ghost',
    activo: 'badge-warning',
};

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });
const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

export default function ArticulosIndex() {
    const [query, setQuery] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('');
    // Aquí sólo quedan los interruptores. El tipo y la clase se cambian en la
    // pantalla de edición: son las dos cosas que cambian cómo se comporta el
    // artículo en todos los movimientos, y de paso los desplegables ensanchaban
    // la tabla hasta dejarla sin espacio para lo que se viene a consultar.
    const [cambios, setCambios] = useState<Record<number, Partial<AlmArticuloDemo>>>({});

    const valorDe = (articulo: AlmArticuloDemo): AlmArticuloDemo => ({ ...articulo, ...cambios[articulo.id] });

    const editar = (id: number, cambio: Partial<AlmArticuloDemo>) =>
        setCambios((prev) => ({ ...prev, [id]: { ...prev[id], ...cambio } }));

    const s = query.trim().toLowerCase();
    // Se busca también por código de barras: el almacenista llega con la caja
    // en la mano y lo que tiene enfrente es el código escaneado, no la
    // descripción con la que se dio de alta. El ID de Steelex entra por lo
    // mismo: quien viene del sistema anterior trae ese dato y no el código
    // nuevo. La marca y el modelo ya no se buscan aquí: son de la pieza, y de
    // eso sabe Activos.
    const visibles = ARTICULOS_DEMO.map(valorDe).filter(
        (i) =>
            (s === '' ||
                i.codigo.toLowerCase().includes(s) ||
                i.descripcion.toLowerCase().includes(s) ||
                (i.idsteelex ?? '').toLowerCase().includes(s) ||
                (i.area ?? '').toLowerCase().includes(s) ||
                (i.codigo_barras ?? '').toLowerCase().includes(s)) &&
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
                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/etiquetas" variant="outline">
                            <TagIcon className="size-4" />
                            Imprimir etiquetas
                        </ButtonLink>
                        <ButtonLink href="/admin/almacen/articulos/create" variant="primary">
                            <PlusIcon className="size-4" />
                            Nuevo artículo
                        </ButtonLink>
                    </div>
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
                                <th className="w-28">Tipo</th>
                                <th className="w-24">Clase</th>
                                <th className="text-center">Por pieza</th>
                                <th className="text-center">Inspección de mantenimiento</th>
                                <th className="text-center">Lleva kardex</th>
                                <th className="text-right">Stock mínimo</th>
                                <th className="text-right">Último precio</th>
                                <th className="text-right">Existencia</th>
                                <th className="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={13} className="text-base-content/50 py-6 text-center">
                                        Ningún artículo coincide con el filtro.
                                    </td>
                                </tr>
                            ) : (
                                visibles.map((i) => {
                                    const bajoMinimo =
                                        i.controla_inventario &&
                                        i.stock_minimo !== null &&
                                        i.existencia_total < i.stock_minimo;
                                    const regla = REGLAS_ABC.find((r) => r.clasificacion === i.clasificacion_abc);

                                    return (
                                        <tr key={i.id} className="hover">
                                            <td>
                                                <MiniaturaArticulo
                                                    url={i.imagen_url}
                                                    descripcion={i.descripcion}
                                                />
                                            </td>
                                            <td>
                                                <Link
                                                    href={`/admin/almacen/articulos/${i.id}`}
                                                    className="link link-hover font-mono font-medium"
                                                >
                                                    {i.codigo}
                                                </Link>
                                                {i.codigo_barras && (
                                                    <BarcodeIcon
                                                        className="text-base-content/40 ml-1 inline size-3"
                                                        aria-label="Tiene código de barras"
                                                    />
                                                )}
                                            </td>
                                            <td>{i.descripcion}</td>
                                            <td className="text-base-content/60 font-mono text-xs">{i.unidad}</td>
                                            <td>
                                                <span className={`badge badge-sm ${CLASE_TIPO[i.tipo]}`}>
                                                    {TIPOS_ARTICULO[i.tipo]}
                                                </span>
                                            </td>
                                            <td>
                                                {/*
                                                 * De aquí sale cada cuánto lo alcanza el inventario cíclico:
                                                 * es la única columna que decide trabajo futuro.
                                                 */}
                                                {i.controla_inventario ? (
                                                    <span
                                                        className={`badge badge-sm ${CLASES_ABC[i.clasificacion_abc]}`}
                                                        title={regla ? `Se cuenta cada ${regla.frecuencia_dias} días` : undefined}
                                                    >
                                                        Clase {i.clasificacion_abc}
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                            <td className="text-center">
                                                {/*
                                                 * Sólo lo que sale y regresa se puede seguir pieza por pieza.
                                                 * Serializar un insumo no tiene sentido: se gasta.
                                                 */}
                                                {i.tipo === 'insumo' ? (
                                                    <span className="text-base-content/30">—</span>
                                                ) : (
                                                    <input
                                                        type="checkbox"
                                                        className="checkbox checkbox-sm"
                                                        checked={i.se_controla_por_pieza}
                                                        onChange={(e) =>
                                                            editar(i.id, { se_controla_por_pieza: e.target.checked })
                                                        }
                                                        aria-label={`Se controla por pieza ${i.codigo}`}
                                                    />
                                                )}
                                            </td>
                                            <td className="text-center">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-sm"
                                                    checked={i.requiere_verificacion}
                                                    onChange={(e) =>
                                                        editar(i.id, { requiere_verificacion: e.target.checked })
                                                    }
                                                    aria-label={`Inspección de mantenimiento ${i.codigo}`}
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
                                            <td className="text-right font-mono text-xs">
                                                {i.precio_ultimo === null ? (
                                                    <span className="text-base-content/40">—</span>
                                                ) : (
                                                    moneda(i.precio_ultimo)
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
                                            <td>
                                                {/*
                                                 * Aquí sólo se palomea; el tipo, la clase y lo demás
                                                 * —descripción, foto, código de barras— se corrigen
                                                 * en la pantalla de edición.
                                                 */}
                                                <Link
                                                    href={`/admin/almacen/articulos/${i.id}/edit`}
                                                    className="btn btn-ghost btn-xs"
                                                    aria-label={`Editar ${i.codigo}`}
                                                    title="Editar artículo"
                                                >
                                                    <PencilIcon className="size-4" />
                                                </Link>
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
                    ahí entra la herramienta, que no es un caso aparte. <strong>Por pieza</strong> es lo que además
                    lleva número de serie y resguardo por persona: sin eso el kardex sabe cuántas pulidoras salieron,
                    pero no quién tiene cuál — por eso la pulidora va marcada y el módulo de andamio no.{' '}
                    <strong>Inspección de mantenimiento</strong> detiene la entrada hasta que alguien revise el estado
                    del equipo, y va aparte porque no todo activo lo necesita. Quitar <strong>lleva kardex</strong> es
                    para lo que se compra pero no se almacena, como un flete — y por eso también lo deja fuera de los
                    conteos y las etiquetas. La <strong>clase</strong> decide cada cuánto lo alcanza el inventario
                    cíclico: A cada mes, B cada trimestre, C cada semestre. El <strong>tipo</strong> y la{' '}
                    <strong>clase</strong> se cambian con el lápiz, en la pantalla del artículo. El{' '}
                    <strong>último precio</strong> no se teclea aquí, sale de lo que cotizaron los proveedores en
                    Compras; el histórico completo está en la ficha del artículo.
                </p>
            </div>
        </AppLayout>
    );
}
