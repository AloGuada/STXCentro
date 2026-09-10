import { Head, Link, router } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosProducto, PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/productos' },
    { title: 'Productos', href: '/admin/costos/productos' },
];

type Props = {
    productos: PaginatedData<CostosProducto>;
    filters: { search?: string; activo?: string };
};

export default function ProductosIndex({ productos, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/costos/productos', { search: search || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catálogo de Productos" />

            <div className="p-6">
                <div className="mb-6 flex items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Catálogo de productos</h1>
                        <p className="mt-1 text-sm text-base-content/60">
                            Códigos, descripciones e histórico de precios. Un producto nuevo se da de alta en Almacén →
                            Artículos, que es donde se decide si es insumo o activo.
                        </p>
                    </div>
                    <Link href="/admin/almacen/articulos/create" className="btn btn-primary btn-sm gap-1">
                        <PlusIcon className="size-4" /> Nuevo artículo
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="mb-4 flex items-center gap-2">
                    <div className="relative flex-1 sm:flex-initial">
                        <SearchIcon className="absolute left-2 top-1/2 size-4 -translate-y-1/2 text-base-content/40" />
                        <input
                            type="text"
                            className="input input-bordered input-sm w-full pl-8 sm:w-80"
                            placeholder="Buscar por código o descripción..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <button type="submit" className="btn btn-sm">Buscar</button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th className="text-right">Precios</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            {productos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="p-8 text-center text-base-content/40">No hay productos</td>
                                </tr>
                            ) : (
                                productos.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="cursor-pointer hover:bg-base-200"
                                        onClick={() => router.visit(`/admin/costos/productos/${p.id}/edit`)}
                                    >
                                        <td className="font-mono text-xs">{p.codigo ?? '—'}</td>
                                        <td>{p.descripcion}</td>
                                        <td>{p.unidad}</td>
                                        <td className="text-right">{p.precios_count ?? 0}</td>
                                        <td>
                                            <span className={`badge badge-sm ${p.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                {p.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {productos.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm text-base-content/70">
                        <span>Página {productos.current_page} de {productos.last_page} · {productos.total} productos</span>
                        <div className="flex gap-2">
                            {productos.prev_page_url && (
                                <Link href={productos.prev_page_url} className="btn btn-sm btn-outline" preserveState>Anterior</Link>
                            )}
                            {productos.next_page_url && (
                                <Link href={productos.next_page_url} className="btn btn-sm btn-outline" preserveState>Siguiente</Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
