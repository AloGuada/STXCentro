import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Permission } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    permissions: Pick<Permission, 'id' | 'name'>[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/admin/roles' },
    { title: 'Nuevo Rol', href: '/admin/roles/create' },
];

export default function RolesCreate({ permissions }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        permissions: [] as number[],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/roles');
    };

    const togglePermission = (permissionId: number) => {
        setData(
            'permissions',
            data.permissions.includes(permissionId)
                ? data.permissions.filter((id) => id !== permissionId)
                : [...data.permissions, permissionId],
        );
    };

    // Group permissions by module (e.g., "sti.tickets.ver" -> "sti")
    const groupedPermissions = permissions.reduce(
        (acc, permission) => {
            const module = permission.name.split('.')[0] || 'general';
            if (!acc[module]) {
                acc[module] = [];
            }
            acc[module].push(permission);
            return acc;
        },
        {} as Record<string, typeof permissions>,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Rol" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Rol</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="name" error={errors.name} required>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Nombre del rol"
                            />
                        </FormField>

                        <FormField
                            label="Permisos"
                            htmlFor="permissions"
                            error={errors.permissions}
                            description="Selecciona los permisos del rol"
                        >
                            <div className="space-y-4">
                                {Object.entries(groupedPermissions).map(([module, perms]) => (
                                    <div key={module} className="rounded-lg border p-3">
                                        <h4 className="mb-2 font-medium capitalize">{module}</h4>
                                        <div className="grid grid-cols-2 gap-2">
                                            {perms.map((permission) => (
                                                <div
                                                    key={permission.id}
                                                    className="flex items-center space-x-2"
                                                >
                                                    <Checkbox
                                                        id={`permission-${permission.id}`}
                                                        checked={data.permissions.includes(permission.id)}
                                                        onCheckedChange={() => togglePermission(permission.id)}
                                                    />
                                                    <Label
                                                        htmlFor={`permission-${permission.id}`}
                                                        className="cursor-pointer text-sm"
                                                    >
                                                        {permission.name}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {permissions.length === 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        No hay permisos disponibles
                                    </p>
                                )}
                            </div>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/roles">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Crear Rol
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
