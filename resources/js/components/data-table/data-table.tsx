import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
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

type DataTableProps<T> = {
    columns: Column<T>[];
    data: T[];
    keyField: keyof T;
    nextPageUrl?: string | null;
    searchable?: boolean;
    searchPlaceholder?: string;
    searchValue?: string;
    createHref?: string;
    createLabel?: string;
    onRowClick?: (item: T) => void;
    emptyMessage?: string;
    title?: string;
};

export function DataTable<T extends Record<string, unknown>>({
    columns,
    data,
    keyField,
    nextPageUrl,
    searchable = true,
    searchPlaceholder = 'Buscar...',
    searchValue = '',
    createHref,
    createLabel = 'Crear',
    onRowClick,
    emptyMessage = 'No hay registros',
    title,
}: DataTableProps<T>) {
    const getCellValue = (item: T, column: Column<T>): ReactNode => {
        if (column.render) {
            return column.render(item);
        }
        const value = item[column.key as keyof T];
        if (value === null || value === undefined) return '-';
        return String(value);
    };

    return (
        <div className="space-y-4">
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
                        <Button asChild>
                            <Link href={createHref}>
                                <PlusIcon className="size-4" />
                                {createLabel}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="rounded-md border">
                <table className="w-full">
                    <thead>
                        <tr className="border-b bg-muted/50">
                            {columns.map((column) => (
                                <th
                                    key={String(column.key)}
                                    className={cn(
                                        'px-4 py-3 text-left text-sm font-medium',
                                        column.className,
                                    )}
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="text-muted-foreground px-4 py-8 text-center"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            data.map((item) => (
                                <tr
                                    key={String(item[keyField])}
                                    onClick={() => onRowClick?.(item)}
                                    className={cn(
                                        'border-b transition-colors hover:bg-muted/50',
                                        onRowClick && 'cursor-pointer',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={String(column.key)}
                                            className={cn('px-4 py-3 text-sm', column.className)}
                                        >
                                            {getCellValue(item, column)}
                                        </td>
                                    ))}
                                </tr>
                            ))
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
