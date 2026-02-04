import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, Tag } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tags', href: '/admin/tags' },
];

const columns: Column<Tag>[] = [
    { key: 'name', label: 'Nombre' },
    { key: 'slug', label: 'Slug' },
    {
        key: 'color',
        label: 'Color',
        render: (tag) =>
            tag.color ? (
                <div className="flex items-center gap-2">
                    <div
                        className="size-4 rounded border"
                        style={{ backgroundColor: tag.color }}
                    />
                    {tag.color}
                </div>
            ) : (
                '-'
            ),
    },
];

type Props = {
    tags: PaginatedData<Tag>;
    filters: { search?: string };
};

export default function TagsIndex({ tags, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tags}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tags..."
                    createHref="/admin/tags/create"
                    createLabel="Nuevo Tag"
                    emptyMessage="No hay tags registrados"
                    getRowHref={(tag) => `/admin/tags/${tag.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
