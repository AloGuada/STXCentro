import { DataTable } from '@/components/data-table/data-table';
import { SearchInput } from '@/components/data-table/search-input';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, Permission, Role } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

type RoleWithCount = Role & {
    permissions_count: number;
    permissions?: Pick<Permission, 'id' | 'name'>[];
};

type Props = {
    roles: PaginatedData<RoleWithCount>;
    filters: { search?: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/admin/roles' },
];

const columns = [
    { key: 'name' as const, label: 'Nombre' },
    { key: 'permissions_count' as const, label: 'Permisos' },
    { key: 'guard_name' as const, label: 'Guard' },
];

export default function RolesIndex({ roles, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Roles</h1>
                    <Button asChild>
                        <Link href="/admin/roles/create">
                            <PlusIcon className="size-4" />
                            Nuevo Rol
                        </Link>
                    </Button>
                </div>

                <div className="mb-4">
                    <SearchInput
                        defaultValue={filters.search}
                        placeholder="Buscar roles..."
                    />
                </div>

                <DataTable
                    data={roles}
                    columns={columns}
                    getRowHref={(role) => `/admin/roles/${role.id}/edit`}
                    emptyMessage="No se encontraron roles"
                />
            </div>
        </AppLayout>
    );
}
