import { DataTable } from '@/components/data-table/data-table';
import { SearchInput } from '@/components/data-table/search-input';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Media, PaginatedData } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

type Props = {
    media: PaginatedData<Media>;
    filters: { search?: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Media', href: '/admin/media' },
];

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

const columns = [
    { key: 'descripcion' as const, label: 'Descripcion' },
    { key: 'mime' as const, label: 'Tipo' },
    {
        key: 'size' as const,
        label: 'Tamano',
        render: (item: Media) => formatFileSize(item.size),
    },
];

export default function MediaIndex({ media, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Media" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Media</h1>
                    <Button asChild>
                        <Link href="/admin/media/create">
                            <PlusIcon className="size-4" />
                            Subir Archivo
                        </Link>
                    </Button>
                </div>

                <div className="mb-4">
                    <SearchInput
                        defaultValue={filters.search}
                        placeholder="Buscar archivos..."
                    />
                </div>

                <DataTable
                    data={media}
                    columns={columns}
                    getRowHref={(item) => `/admin/media/${item.id}/edit`}
                    emptyMessage="No se encontraron archivos"
                />
            </div>
        </AppLayout>
    );
}
