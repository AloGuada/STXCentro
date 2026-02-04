import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, Role, Usuario } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuarios', href: '/admin/usuarios' },
];

const columns: Column<Usuario>[] = [
    { key: 'name', label: 'Nombre' },
    { key: 'email', label: 'Email' },
    { key: 'empleado', label: 'Empleado', render: (u) => u.empleado ?? '-' },
    {
        key: 'roles',
        label: 'Roles',
        render: (usuario) => (
            <div className="flex flex-wrap gap-1">
                {usuario.roles?.map((role: Role) => (
                    <Badge key={role.id} variant="secondary">
                        {role.name}
                    </Badge>
                )) ?? '-'}
            </div>
        ),
    },
];

type Props = {
    usuarios: PaginatedData<Usuario>;
    filters: { search?: string };
};

export default function UsuariosIndex({ usuarios, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={usuarios}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar usuarios..."
                    createHref="/admin/usuarios/create"
                    createLabel="Nuevo Usuario"
                    emptyMessage="No hay usuarios registrados"
                    getRowHref={(usuario) => `/admin/usuarios/${usuario.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
