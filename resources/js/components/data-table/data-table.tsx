import { ButtonLink } from '@/components/ui/button';
import type { PaginatedData } from '@/types/models';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, ChevronLeftIcon, ChevronRightIcon, PlusIcon } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { SearchInput } from './search-input';

export type Column<T> = {
    key: keyof T | string;
    label: string;
    render?: (item: T) => ReactNode;
    className?: string;
    sortable?: boolean;
    sortKey?: string;
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
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
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
    sortBy,
    sortDir,
    children,
}: PropsWithChildren<DataTableProps<T>>) {
    const isPaginated = !Array.isArray(data) && 'current_page' in data;
    const items = Array.isArray(data) ? data : data.data;

    const handleSort = (column: Column<T>) => {
        if (!column.sortable) return;
        const key = column.sortKey ?? String(column.key);
        const currentParams = Object.fromEntries(new URLSearchParams(window.location.search));
        const newDir = sortBy === key && sortDir === 'asc' ? 'desc' : 'asc';
        router.get(
            window.location.pathname,
            { ...currentParams, sort_by: key, sort_dir: newDir, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const getSortIcon = (column: Column<T>) => {
        const key = column.sortKey ?? String(column.key);
        if (sortBy !== key) return <ArrowUpDownIcon className="text-base-content/30 size-3.5" />;
        return sortDir === 'asc'
            ? <ArrowUpIcon className="size-3.5" />
            : <ArrowDownIcon className="size-3.5" />;
    };

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
                        {children}
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

            <div className="overflow-auto rounded-box border border-base-300" style={{ maxHeight: '70vh' }}>
                <table className="table">
                    <thead className="sticky top-0 z-10 bg-base-100">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={String(column.key)}
                                    className={cn(column.className, column.sortable && 'cursor-pointer select-none')}
                                    onClick={() => column.sortable && handleSort(column)}
                                >
                                    {column.sortable ? (
                                        <span className="inline-flex items-center gap-1">
                                            {column.label}
                                            {getSortIcon(column)}
                                        </span>
                                    ) : (
                                        column.label
                                    )}
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

            {isPaginated && (data as PaginatedData<T>).last_page > 1 && (
                <Pagination data={data as PaginatedData<T>} />
            )}
        </div>
    );
}

function Pagination<T>({ data }: { data: PaginatedData<T> }) {
    const pages: (number | '...')[] = [];
    const current = data.current_page;
    const last = data.last_page;

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

    // Preserva el query string actual (sort_by, sort_dir, search, ...) al paginar.
    // Construimos todos los enlaces aquí en vez de usar prev/next_page_url del
    // backend, que solo conservan esos params si el paginador hizo withQueryString().
    const pageUrl = (page: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(page));
        return `${data.path}?${params.toString()}`;
    };

    return (
        <div className="flex items-center justify-between">
            <span className="text-base-content/60 text-sm">
                {data.from}–{data.to} de {data.total}
            </span>
            <div className="join">
                {current > 1 ? (
                    <Link href={pageUrl(current - 1)} className="join-item btn btn-sm" preserveState preserveScroll>
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

                {current < last ? (
                    <Link href={pageUrl(current + 1)} className="join-item btn btn-sm" preserveState preserveScroll>
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
