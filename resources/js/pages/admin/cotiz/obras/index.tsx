import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizObra, PaginatedData } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import {
    type LucideIcon,
    CreditCardIcon,
    LayersIcon,
    SlidersHorizontalIcon,
} from 'lucide-react';
import type { MouseEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/obras' },
    { title: 'Obras', href: '/admin/cotiz/obras' },
];

/**
 * Botón de acción dentro de una fila que ya es un <Link> (getRowHref). Usamos un <button>
 * (no un <Link>) para no anidar <a> dentro de <a>, y navegamos con router.visit.
 */
function AccionFila({
    href,
    icon: Icon,
    label,
}: {
    href: string;
    icon: LucideIcon;
    label: string;
}) {
    return (
        <span
            role="link"
            tabIndex={0}
            className="inline-flex cursor-pointer link items-center gap-1 text-xs link-primary"
            // <span> (no <a>/<button>) para no anidar interactivos dentro del <Link> de la fila.
            // preventDefault corta la navegación nativa del ancla; stopPropagation, el onClick de Inertia.
            onClick={(e: MouseEvent) => {
                e.preventDefault();
                e.stopPropagation();
                router.visit(href);
            }}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    router.visit(href);
                }
            }}
        >
            <Icon className="size-3.5" />
            {label}
        </span>
    );
}

const columns: Column<CotizObra>[] = [
    { key: 'nombre', label: 'Nombre' },
    {
        key: 'op',
        label: 'OP',
        render: (o) => o.op ?? '-',
    },
    {
        key: 'factor_contratista',
        label: 'Factor contratista',
        render: (o) => Number(o.factor_contratista).toFixed(2),
    },
    { key: 'num_grupos', label: 'Grupos' },
    {
        key: 'generadoras_count',
        label: 'Generadoras',
        render: (o) => (
            <div className="flex items-center gap-2">
                <span className="badge badge-ghost badge-sm">
                    {o.generadoras_count ?? 0}
                </span>
                <AccionFila
                    href={`/admin/cotiz/obras/${o.id}/generadoras`}
                    icon={LayersIcon}
                    label="Ver generadoras"
                />
            </div>
        ),
    },
    {
        key: 'tarjetas',
        label: 'Tarjetas',
        render: (o) => (
            <AccionFila
                href={`/admin/cotiz/obras/${o.id}/tarjetas`}
                icon={CreditCardIcon}
                label="Ver tarjetas"
            />
        ),
    },
    {
        key: 'catalogo',
        label: 'Catálogo',
        render: (o) => (
            <AccionFila
                href={`/admin/cotiz/obras/${o.id}/catalogo`}
                icon={SlidersHorizontalIcon}
                label="Overrides"
            />
        ),
    },
];

type Props = {
    obras: PaginatedData<CotizObra>;
    filters: { search?: string };
};

export default function ObrasIndex({ obras, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={obras}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar obras..."
                    createHref="/admin/cotiz/obras/create"
                    createLabel="Nueva obra"
                    emptyMessage="No hay obras registradas"
                    getRowHref={(o) => `/admin/cotiz/obras/${o.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
