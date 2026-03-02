import { ButtonLink } from '@/components/ui/button';
import type { PaginatedData } from '@/types/models';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { SearchInput } from './search-input';

export type Column<T> = {
    key: keyof T | string;
    label: string;
    render?: (item: T) => ReactNode;
    className?: string;
};

type DataTableProps<T extends { id: number | string }> = {
    columns: Column<T>[];
    data: PaginatedData<T> | T[];
    searchable?: boolean;
    searchPlaceholder?: string;
    searchValue?: string;
    createHref?: string;
    createLabel?: string;
    getRowHref?: (item: T) => string;
    onRowClick?: (item: T) => void;
    emptyMessage?: string;
    title?: string;
};

export function DataTable<T extends { id: number | string }>({
    columns,
    data,
    searchable = false,
    searchPlaceholder = 'Buscar...',
    searchValue = '',
    createHref,
    createLabel = 'Crear',
    getRowHref,
    onRowClick,
    emptyMessage = 'No hay registros',
    title,
}: DataTableProps<T>) {
    // Normalizar datos: soporta tanto array simple como PaginatedData
    const items = Array.isArray(data) ? data : data.data;
    const nextPageUrl = Array.isArray(data) ? null : data.links?.next;

    const getCellValue = (item: T, column: Column<T>): ReactNode => {
        if (column.render) {
            return column.render(item);
        }
        const value = item[column.key as keyof T];
        if (value === null || value === undefined) return '-';
        return String(value);
    };

    const renderRow = (item: T) => {
        const rowClassName = cn(
            'hover',
            (getRowHref || onRowClick) && 'cursor-pointer',
        );

        if (getRowHref) {
            return (
                <tr key={String(item.id)} className={rowClassName}>
                    {columns.map((column) => (
                        <td key={String(column.key)} className={column.className}>
                            <Link href={getRowHref(item)} className="block">
                                {getCellValue(item, column)}
                            </Link>
                        </td>
                    ))}
                </tr>
            );
        }

        return (
            <tr
                key={String(item.id)}
                onClick={() => onRowClick?.(item)}
                className={rowClassName}
            >
                {columns.map((column) => (
                    <td key={String(column.key)} className={column.className}>
                        {getCellValue(item, column)}
                    </td>
                ))}
            </tr>
        );
    };

    return (
        <div className="space-y-4">
            {(title || searchable || createHref) && (
                <div className="flex items-center justify-between gap-4">
                    {title && <h2 className="text-lg font-semibold">{title}</h2>}
                    <div className="flex flex-1 items-center justify-end gap-4">
                        {searchable && (
                            <SearchInput
                                placeholder={searchPlaceholder}
                                defaultValue={searchValue}
                                className="max-w-xs"
                            />
                        )}
                        {createHref && (
                            <ButtonLink href={createHref} variant="primary">
                                <PlusIcon className="size-4" />
                                {createLabel}
                            </ButtonLink>
                        )}
                    </div>
                </div>
            )}

            <div className="overflow-x-auto rounded-box border border-base-300">
                <table className="table">
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th key={String(column.key)} className={column.className}>
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="text-base-content/60 text-center py-8"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            items.map((item) => renderRow(item))
                        )}
                    </tbody>
                </table>
            </div>

            {!Array.isArray(data) && data.meta.last_page > 1 && (
                <Pagination meta={data.meta} links={data.links} />
            )}
        </div>
    );
}

function Pagination({ meta, links }: { meta: PaginatedData<unknown>['meta']; links: PaginatedData<unknown>['links'] }) {
    const pages: (number | '...')[] = [];
    const current = meta.current_page;
    const last = meta.last_page;

    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
    } else {
        pages.push(1);
        if (current > 3) pages.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
            pages.push(i);
        }
        if (current < last - 2) pages.push('...');
        pages.push(last);
    }

    const basePath = meta.path;
    const pageUrl = (page: number) => `${basePath}?page=${page}`;

    return (
        <div className="flex items-center justify-between">
            <span className="text-base-content/60 text-sm">
                {meta.from}–{meta.to} de {meta.total}
            </span>
            <div className="join">
                {links.prev ? (
                    <Link href={links.prev} className="join-item btn btn-sm" preserveState preserveScroll>
                        <ChevronLeftIcon className="size-4" />
                    </Link>
                ) : (
                    <button className="join-item btn btn-sm btn-disabled" disabled>
                        <ChevronLeftIcon className="size-4" />
                    </button>
                )}

                {pages.map((page, i) =>
                    page === '...' ? (
                        <button key={`dots-${i}`} className="join-item btn btn-sm btn-disabled" disabled>
                            ...
                        </button>
                    ) : (
                        <Link
                            key={page}
                            href={pageUrl(page)}
                            className={cn('join-item btn btn-sm', page === current && 'btn-active')}
                            preserveState
                            preserveScroll
                        >
                            {page}
                        </Link>
                    ),
                )}

                {links.next ? (
                    <Link href={links.next} className="join-item btn btn-sm" preserveState preserveScroll>
                        <ChevronRightIcon className="size-4" />
                    </Link>
                ) : (
                    <button className="join-item btn btn-sm btn-disabled" disabled>
                        <ChevronRightIcon className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}
