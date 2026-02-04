import { ButtonLink } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { PaginatedData } from '@/types/models';
import { cn } from '@/lib/utils';
import { Link, WhenVisible } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
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

            {nextPageUrl && (
                <WhenVisible
                    always
                    params={{
                        data: {
                            only: ['data'],
                        },
                    }}
                    fallback={<TableSkeleton columns={columns.length} />}
                >
                    <div />
                </WhenVisible>
            )}
        </div>
    );
}

function TableSkeleton({ columns, rows = 3 }: { columns: number; rows?: number }) {
    return (
        <div className="space-y-2">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="flex gap-4 px-4 py-3">
                    {Array.from({ length: columns }).map((_, j) => (
                        <Skeleton key={j} className="h-5 flex-1" />
                    ))}
                </div>
            ))}
        </div>
    );
}
