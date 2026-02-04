import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Role, Usuario } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    usuario: Usuario;
    roles: Pick<Role, 'id' | 'name'>[];
};

export default function UsuariosEdit({ usuario, roles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Usuarios', href: '/admin/usuarios' },
        { title: usuario.name, href: `/admin/usuarios/${usuario.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        empleado: usuario.empleado?.toString() ?? '',
        name: usuario.name,
        email: usuario.email,
        password: '',
        roles: usuario.roles?.map((r) => r.id) ?? [],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/usuarios/${usuario.id}`);
    };

    const toggleRole = (roleId: number) => {
        setData(
            'roles',
            data.roles.includes(roleId)
                ? data.roles.filter((id) => id !== roleId)
                : [...data.roles, roleId],
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${usuario.name}`} />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Usuario</CardTitle>
                        <DeleteDialog
                            title="Eliminar usuario"
                            description={`¿Estás seguro de eliminar a "${usuario.name}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/usuarios/${usuario.id}`}
                        />
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre" htmlFor="name" error={errors.name} required>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Nombre completo"
                                />
                            </FormField>

                            <FormField label="Email" htmlFor="email" error={errors.email} required>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="correo@ejemplo.com"
                                />
                            </FormField>

                            <FormField
                                label="Contraseña"
                                htmlFor="password"
                                error={errors.password}
                                description="Dejar vacío para mantener la contraseña actual"
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Nueva contraseña"
                                />
                            </FormField>

                            <FormField
                                label="ID Empleado"
                                htmlFor="empleado"
                                error={errors.empleado}
                                description="Identificador del empleado en sistema externo (opcional)"
                            >
                                <Input
                                    id="empleado"
                                    type="number"
                                    value={data.empleado}
                                    onChange={(e) => setData('empleado', e.target.value)}
                                    placeholder="Ej: 12345"
                                />
                            </FormField>

                            <FormField
                                label="Roles"
                                htmlFor="roles"
                                error={errors.roles}
                                description="Selecciona los roles del usuario"
                            >
                                <div className="grid grid-cols-2 gap-2">
                                    {roles.map((role) => (
                                        <div
                                            key={role.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                checked={data.roles.includes(role.id)}
                                                onCheckedChange={() => toggleRole(role.id)}
                                            />
                                            <Label
                                                htmlFor={`role-${role.id}`}
                                                className="cursor-pointer"
                                            >
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/usuarios">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
