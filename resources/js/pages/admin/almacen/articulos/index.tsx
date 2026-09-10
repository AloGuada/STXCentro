import { Head, Link, router } from '@inertiajs/react';
import { BarcodeIcon, PencilIcon, PlusIcon, TagIcon, TriangleAlertIcon } from 'lucide-react';
import { MiniaturaArticulo } from '@/components/alm/miniatura-articulo';
import { DataTable, type Column } from '@/components/data-table';
import { ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmArticulo, AlmOpcion, AlmOpcionClase, AlmProductoTipo, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Artículos', href: '/admin/almacen/articulos' },
];

const CLASE_TIPO: Record<AlmProductoTipo, string> = {
    insumo: 'badge-ghost',
    activo: 'badge-warning',
};

const CLASE_ABC: Record<string, string> = {
    A: 'badge-error',
    B: 'badge-warning',
    C: 'badge-ghost',
};

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });
const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type Props = {
    articulos: PaginatedData<AlmArticulo>;
    filters: { search?: string; tipo?: string; area_id?: string; clase?: string; sin_ligar?: boolean; inactivos?: boolean };
    /** Lo que Compras tecleó al vuelo y todavía no entra al kardex. */
    /** Material que la bodega guarda y que nadie ha emparejado con Compras. */
    sinLigar: number;
    areas: AlmArea[];
    tipos: AlmOpcion[];
    clases: AlmOpcionClase[];
};

export default function ArticulosIndex({ articulos, filters, sinLigar, areas, tipos, clases }: Props) {
    /** Los filtros se acumulan sobre los que ya estaban, y siempre vuelven a la página 1. */
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/articulos', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    const columns: Column<AlmArticulo>[] = [
        {
            key: 'imagen_url',
            label: '',
            className: 'w-14',
            render: (a) => <MiniaturaArticulo url={a.imagen_url} descripcion={a.descripcion} />,
        },
        {
            key: 'codigo',
            label: 'Código',
            render: (a) => (
                <>
                    <Link
                        href={`/admin/almacen/articulos/${a.id}`}
                        className="link link-hover font-mono font-medium"
                    >
                        {a.codigo ?? <span className="text-base-content/40 italic">Sin código</span>}
                    </Link>
                    {a.codigo_barras && (
                        <BarcodeIcon
                            className="text-base-content/40 ml-1 inline size-3"
                            aria-label="Tiene código de barras"
                        />
                    )}
                    {!a.activo && <span className="badge badge-xs badge-error ml-1">Inactivo</span>}
                </>
            ),
        },
        { key: 'descripcion', label: 'Descripción' },
        {
            key: 'unidad',
            label: 'Unidad',
            className: 'text-base-content/60 font-mono text-xs',
        },
        {
            key: 'tipo',
            label: 'Tipo',
            className: 'w-28',
            render: (a) => (
                <span className={`badge badge-sm ${CLASE_TIPO[a.tipo]}`}>
                    {tipos.find((t) => t.value === a.tipo)?.label ?? a.tipo}
                </span>
            ),
        },
        {
            // De aquí sale cada cuánto lo alcanza el inventario cíclico: es la
            // única columna que decide trabajo futuro.
            key: 'clasificacion_abc',
            label: 'Clase',
            className: 'w-24',
            render: (a) => {
                const regla = clases.find((c) => c.value === a.clasificacion_abc);

                return (
                    <span
                        className={`badge badge-sm ${CLASE_ABC[a.clasificacion_abc] ?? 'badge-ghost'}`}
                        title={regla ? `Se cuenta cada ${regla.frecuencia_dias} días` : undefined}
                    >
                        Clase {a.clasificacion_abc}
                    </span>
                );
            },
        },
        {
            key: 'se_controla_por_pieza',
            label: 'Por pieza',
            className: 'text-center',
            render: (a) =>
                // Serializar un insumo no tiene sentido: se gasta.
                a.tipo === 'insumo' ? (
                    <span className="text-base-content/30">—</span>
                ) : a.se_controla_por_pieza ? (
                    <span className="badge badge-sm badge-info">Sí</span>
                ) : (
                    <span className="text-base-content/40 text-sm">No</span>
                ),
        },
        {
            key: 'requiere_verificacion',
            label: 'Inspección',
            className: 'text-center',
            render: (a) =>
                a.requiere_verificacion ? (
                    <span className="badge badge-sm badge-warning">Sí</span>
                ) : (
                    <span className="text-base-content/40 text-sm">No</span>
                ),
        },
        {
            // Ya no se pregunta si lleva kardex: estar en este catálogo es
            // llevarlo. Lo que sí varía es si Compras ya sabe comprarlo.
            key: 'producto_id',
            label: 'En Compras',
            className: 'text-center',
            render: (a) =>
                a.producto_id !== null ? (
                    <span className="badge badge-sm badge-success">Sí</span>
                ) : (
                    <span className="badge badge-sm badge-warning" title="Todavía no se empareja con un producto de Compras">
                        Pendiente
                    </span>
                ),
        },
        {
            key: 'stock_minimo',
            label: 'Stock mínimo',
            className: 'text-right',
            render: (a) =>
                a.stock_minimo === null ? (
                    <span className="text-base-content/30">—</span>
                ) : (
                    <span className="font-mono">{numero(a.stock_minimo)}</span>
                ),
        },
        {
            key: 'precio_ultimo',
            label: 'Último precio',
            className: 'text-right',
            render: (a) =>
                a.precio_ultimo === null ? (
                    <span className="text-base-content/30" title="Todavía no se ha cotizado ni comprado">
                        —
                    </span>
                ) : (
                    <span className="font-mono">{moneda(a.precio_ultimo)}</span>
                ),
        },
        {
            key: 'existencia_total',
            label: 'Existencia',
            className: 'text-right',
            render: (a) => {
                const bajoMinimo = a.stock_minimo !== null && a.existencia_total < a.stock_minimo;

                return (
                    <span className={`font-mono ${bajoMinimo ? 'text-error font-medium' : ''}`}>
                        {numero(a.existencia_total)}
                        {bajoMinimo && (
                            <TriangleAlertIcon
                                className="ml-1 inline size-3"
                                aria-label="Por debajo del stock mínimo"
                            />
                        )}
                    </span>
                );
            },
        },
        {
            key: 'acciones',
            label: '',
            className: 'w-10',
            render: (a) => (
                <Link
                    href={`/admin/almacen/articulos/${a.id}/edit`}
                    className="btn btn-ghost btn-xs"
                    title="Editar"
                >
                    <PencilIcon className="size-3.5" />
                </Link>
            ),
        },
    ];

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

                {sinLigar > 0 && !filters.sin_ligar && (
                    <div className="alert alert-info mb-4">
                        <span>
                            {sinLigar} artículo(s) que la bodega guarda todavía no se emparejan con un producto de Compras. Mientras
                            nadie los clasifique, comprarlos no mueve existencia.
                        </span>
                        <button className="btn btn-sm" onClick={() => filtrar({ sin_ligar: '1' })}>
                            Ver la bandeja
                        </button>
                    </div>
                )}

                {filters.sin_ligar && (
                    <div className="alert alert-warning mb-4">
                        <span>Viendo sólo lo que falta clasificar.</span>
                        <button className="btn btn-sm" onClick={() => filtrar({ sin_ligar: undefined })}>
                            Ver todo el catálogo
                        </button>
                    </div>
                )}

                <DataTable
                    columns={columns}
                    data={articulos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por código, descripción, barras o ID Steelex..."
                    emptyMessage="Ningún artículo coincide con el filtro."
                >
                    <div className="w-44">
                        <Select
                            value={filters.tipo ?? ''}
                            onValueChange={(v) => filtrar({ tipo: v || undefined })}
                            placeholder="Todos los tipos"
                        >
                            {tipos.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-44">
                        <Select
                            value={filters.area_id ?? ''}
                            onValueChange={(v) => filtrar({ area_id: v || undefined })}
                            placeholder="Todas las áreas"
                        >
                            {areas.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <Select
                            value={filters.clase ?? ''}
                            onValueChange={(v) => filtrar({ clase: v || undefined })}
                            placeholder="Todas las clases"
                        >
                            {clases.map((c) => (
                                <SelectItem key={c.value} value={c.value}>
                                    Clase {c.value} — {c.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <label className="flex cursor-pointer items-center gap-2 pb-3">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={Boolean(filters.inactivos)}
                            onChange={(e) => filtrar({ inactivos: e.target.checked ? '1' : undefined })}
                        />
                        <span className="text-sm">Ver inactivos</span>
                    </label>
                </DataTable>
            </div>
        </AppLayout>
    );
}
