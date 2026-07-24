import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRecepcionRow, PaginatedData } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { FileTextIcon, PackageCheckIcon, ReceiptIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Recepciones', href: '/admin/costos/recepciones' },
];

type Props = {
    recepciones: PaginatedData<CostosRecepcionRow>;
    filters: { search?: string; tipo?: string };
};

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

export default function RecepcionesIndex({ recepciones, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [tipo, setTipo] = useState(filters.tipo ?? '');

    const aplicarFiltros = (next: { search?: string; tipo?: string }) => {
        router.get(
            '/admin/costos/recepciones',
            { search: next.search ?? search, tipo: next.tipo ?? tipo },
            { preserveState: true, replace: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        aplicarFiltros({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recepciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <PackageCheckIcon className="h-6 w-6" /> Recepciones
                    </h1>
                    <p className="mt-1 text-sm text-base-content/60">
                        Recepciones de almacén ligadas a su orden de compra y solicitud de pago.
                    </p>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <form onSubmit={handleSearch} className="flex items-end gap-2">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-base-content/60">Buscar</span>
                            <input
                                type="text"
                                className="input input-bordered input-sm w-64"
                                placeholder="Folio REC, OC o proveedor…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </label>
                        <button type="submit" className="btn btn-sm btn-primary gap-1">
                            <SearchIcon className="h-4 w-4" /> Buscar
                        </button>
                    </form>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-base-content/60">Tipo</span>
                        <select
                            className="select select-bordered select-sm"
                            value={tipo}
                            onChange={(e) => {
                                setTipo(e.target.value);
                                aplicarFiltros({ tipo: e.target.value });
                            }}
                        >
                            <option value="">Todos</option>
                            <option value="parcial">Parcial</option>
                            <option value="completa">Completa</option>
                        </select>
                    </label>
                </div>

                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>OC</th>
                                <th>Solicitud de Pago</th>
                                <th>Proveedor</th>
                                <th>Obra</th>
                                <th>Factura</th>
                                <th>Recibió</th>
                                <th>Tipo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {recepciones.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="py-12 text-center text-base-content/60">
                                        No hay recepciones.
                                    </td>
                                </tr>
                            ) : (
                                recepciones.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="font-medium">{r.folio ?? '-'}</td>
                                        <td className="whitespace-nowrap">{fmtDate(r.fecha_entrega)}</td>
                                        <td>
                                            {r.oc ? (
                                                <Link href={r.oc.url} className="link link-primary flex items-center gap-1">
                                                    <FileTextIcon className="h-3 w-3" /> {r.oc.folio}
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td>
                                            {r.solicitudes_pago.length === 0 ? (
                                                <span className="text-base-content/40">-</span>
                                            ) : (
                                                <div className="flex flex-col gap-1">
                                                    {r.solicitudes_pago.map((sp) => (
                                                        <Link key={sp.id} href={sp.url} className="link link-primary flex items-center gap-1">
                                                            <ReceiptIcon className="h-3 w-3" /> {sp.folio}
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td>{r.proveedor ?? '-'}</td>
                                        <td className="max-w-xs truncate">{r.obra ?? '-'}</td>
                                        <td>{r.factura?.folio ?? '-'}</td>
                                        <td>{r.recibido_por ?? '-'}</td>
                                        <td>
                                            <span
                                                className={`badge badge-sm ${r.tipo === 'completa' ? 'badge-success' : 'badge-warning'}`}
                                            >
                                                {r.tipo === 'completa' ? 'Completa' : 'Parcial'}
                                            </span>
                                        </td>
                                        <td>
                                            <a href={r.pdf_url} target="_blank" rel="noopener noreferrer" className="btn btn-ghost btn-xs">
                                                PDF
                                            </a>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {recepciones.last_page > 1 && (
                    <div className="mt-4 flex flex-col items-start gap-2 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            Página {recepciones.current_page} de {recepciones.last_page} · {recepciones.total} recepciones
                        </span>
                        <div className="flex gap-2">
                            {recepciones.prev_page_url && (
                                <Link href={recepciones.prev_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Anterior
                                </Link>
                            )}
                            {recepciones.next_page_url && (
                                <Link href={recepciones.next_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Siguiente
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
