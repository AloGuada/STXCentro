import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Role } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuarios', href: '/admin/usuarios' },
    { title: 'Nuevo Usuario', href: '/admin/usuarios/create' },
];

type Props = {
    roles: Pick<Role, 'id' | 'name'>[];
};

export default function UsuariosCreate({ roles }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        empleado: '',
        name: '',
        email: '',
        password: '',
        roles: [] as number[],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/usuarios');
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
            <Head title="Nuevo Usuario" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Usuario</h1>
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
                            label="Contrasena"
                            htmlFor="password"
                            error={errors.password}
                            required
                        >
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Minimo 8 caracteres"
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
                </div>
            </div>
        </AppLayout>
    );
}
